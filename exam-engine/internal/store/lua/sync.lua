-- Sync / resync: heartbeat, proctor batch, atomic rev check + apply + agg update.
-- KEYS: 1 att, 2 agg, 3 events, 4 seen, 5 ctl
-- ARGV: 1 now, 2 dev, 3 mode (hb|apply|replace|resync), 4 rev (base/expected), 5 client checksum (hb),
--       6 new answers JSON, 7 new checksum, 8 answered count, 9 changed count, 10 flagged JSON ('' = keep),
--       11 grace_ms, 12 proctor data ('' = none), 13 proctor count, 14 run, 15 reg, 16 maxlen
-- Returns {status, rev, deadline_ms}; status is an action (none|submit) or resync|lock|hold|late|submitted|
-- not_started|gone|revoked. deadline_ms is the effective deadline (stored + run-level extra).
local now = tonumber(ARGV[1])
local f = redis.call('HMGET', KEYS[1], 'state', 'dev', 'lk', 'dl', 'rev', 'cks', 'flg', 'fl', 'sid')
local state = f[1]
if not state then return {'gone', 0, 0} end
if f[2] ~= ARGV[2] then return {'revoked', 0, 0} end
local rx, hold = runctl(KEYS[5])
local rev, dl = num(f[5]), num(f[4]) + rx
if state == 'submitted' then return {'submitted', rev, dl} end
redis.call('HSET', KEYS[1], 'ls', istr(now))
if state ~= 'writing' then
  if f[3] == '1' then return {'lock', rev, 0} end
  return {'not_started', rev, 0}
end
redis.call('ZADD', KEYS[4], istr(now), ARGV[15])
local pc = tonumber(ARGV[13]) or 0
if pc > 0 then
  xadd(KEYS[3], ARGV[16], 'proctor.batch', ARGV[14], ARGV[15], iso(now), ARGV[12])
  redis.call('HINCRBY', KEYS[1], 'fl', pc)
  if num(f[8]) == 0 then
    redis.call('HINCRBY', KEYS[2], 'fl', 1)
    redis.call('HINCRBY', KEYS[2], 's:' .. (f[9] or '') .. ':fl', 1)
  end
end
if f[3] == '1' then return {'lock', rev, dl} end
if hold and now > dl then return {'hold', rev, dl} end
if now > dl + tonumber(ARGV[11]) then return {'late', rev, dl} end
local action = 'none'
if now > dl then action = 'submit' end
local mode = ARGV[3]
if mode == 'resync' then return {'resync', rev, dl} end
local changed = false
if mode == 'hb' then
  if tonumber(ARGV[4]) ~= rev or ARGV[5] ~= f[6] then return {'resync', rev, dl} end
else
  if mode == 'apply' and tonumber(ARGV[4]) ~= rev then return {'resync', rev, dl} end
  if ARGV[7] ~= f[6] then
    redis.call('HSET', KEYS[1], 'ans', ARGV[6], 'cks', ARGV[7], 'n', ARGV[8])
    if now > dl then redis.call('HINCRBY', KEYS[1], 'late', tonumber(ARGV[9]) or 0) end
    changed = true
  end
end
if ARGV[10] ~= '' and ARGV[10] ~= f[7] then
  redis.call('HSET', KEYS[1], 'flg', ARGV[10])
  changed = true
end
if changed then
  rev = rev + 1
  redis.call('HSET', KEYS[1], 'rev', istr(rev))
end
return {action, rev, dl}
