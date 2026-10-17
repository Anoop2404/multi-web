-- Finalise an attempt (client submit, sweeper auto-submit, admin force_submit, close).
-- KEYS: 1 att, 2 agg, 3 events, 4 dlz, 5 seen, 6 ctl
-- ARGV: 1 now, 2 dev ('' = no device check), 3 requested mode (manual|auto|forced),
--       4 kind (none|hb|apply|replace), 5 expected rev, 6 client checksum (hb), 7 new answers JSON,
--       8 new checksum, 9 answered count, 10 changed count, 11 grace_ms, 12 receipt, 13 run, 14 reg,
--       15 maxlen, 16 only_if_due ('1' = only when past deadline + grace)
-- Returns {status, receipt, submitted_ms, mode, rev}.
local now = tonumber(ARGV[1])
local f = redis.call('HMGET', KEYS[1], 'state', 'dev', 'lk', 'dl', 'rev', 'cks', 'ans', 'sid', 'rcpt', 'sub', 'mode', 'late')
local state = f[1]
if not state then return {'gone', '', 0, '', 0} end
if ARGV[2] ~= '' and f[2] ~= ARGV[2] then return {'revoked', '', 0, '', 0} end
if state == 'submitted' then return {'already', f[9] or '', num(f[10]), f[11] or '', num(f[5])} end
if state ~= 'writing' then return {'not_started', '', 0, '', 0} end
local reqmode = ARGV[3]
if reqmode == 'manual' and f[3] == '1' then return {'locked', '', 0, '', 0} end
local rx, hold = runctl(KEYS[6])
local dl = num(f[4]) + rx
local grace = tonumber(ARGV[11])
if ARGV[16] == '1' and (hold or now <= dl + grace) then return {'not_due', '', 0, '', 0} end
if reqmode == 'manual' and hold and now > dl then return {'held', '', 0, '', 0} end
local mode = reqmode
if reqmode == 'manual' and now > dl then mode = 'auto' end
local rev = num(f[5])
local ans = f[7] or '{}'
local late = num(f[12])
local kind = ARGV[4]
if kind ~= 'none' and now <= dl + grace then
  if kind == 'hb' then
    if ARGV[6] ~= f[6] then return {'resync', '', 0, '', rev} end
  else
    if kind == 'apply' and tonumber(ARGV[5]) ~= rev then return {'resync', '', 0, '', rev} end
    if ARGV[8] ~= f[6] then
      ans = ARGV[7]
      rev = rev + 1
      redis.call('HSET', KEYS[1], 'ans', ans, 'cks', ARGV[8], 'n', ARGV[9], 'rev', istr(rev))
      if now > dl then
        late = late + (tonumber(ARGV[10]) or 0)
        redis.call('HSET', KEYS[1], 'late', istr(late))
      end
    end
  end
end
redis.call('HSET', KEYS[1], 'state', 'submitted', 'sub', istr(now), 'mode', mode, 'rcpt', ARGV[12])
if f[3] == '1' then
  redis.call('HSET', KEYS[1], 'lk', '0')
  redis.call('HINCRBY', KEYS[2], 'lk', -1)
end
redis.call('ZREM', KEYS[4], ARGV[14])
redis.call('ZREM', KEYS[5], ARGV[14])
redis.call('HINCRBY', KEYS[2], 'sub', 1)
redis.call('HINCRBY', KEYS[2], 's:' .. (f[8] or '') .. ':sub', 1)
local data = '{"mode":"' .. mode .. '","rev":' .. istr(rev) .. ',"answers":' .. ans ..
  ',"late_changes":' .. istr(late) .. ',"receipt":"' .. ARGV[12] .. '"}'
xadd(KEYS[3], ARGV[15], 'attempt.submitted', ARGV[13], ARGV[14], iso(now), data)
return {'submitted', ARGV[12], now, mode, rev}
