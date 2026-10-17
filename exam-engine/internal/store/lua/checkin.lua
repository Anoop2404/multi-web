-- Login: bind a new device to the attempt (creating it on first check-in).
-- KEYS: 1 att, 2 agg, 3 events
-- ARGV: 1 now, 2 dev, 3 idle_ms, 4 sid, 5 ip, 6 ua, 7 method, 8 run, 9 reg, 10 session token,
--       11 maxlen, 12 checked_in data, 13 device_switched data
local now = tonumber(ARGV[1])
local f = redis.call('HMGET', KEYS[1], 'state', 'dev', 'ls', 'asw', 'sk')
local state, dev, ls, asw, oldsk = f[1], f[2], f[3], f[4], f[5]
local switched = false
if dev and dev ~= '' then
  if now - num(ls) < tonumber(ARGV[3]) and asw ~= '1' then
    return {'conflict', '', state or ''}
  end
  switched = true
end
local at = iso(now)
if not state then
  redis.call('HSET', KEYS[1], 'state', 'checked_in', 'ci', istr(now), 'sid', ARGV[4], 'rev', '0',
    'cks', EMPTY_CKS, 'ans', '{}', 'flg', '[]', 'n', '0', 'fl', '0', 'late', '0')
  redis.call('HINCRBY', KEYS[2], 'ci', 1)
  redis.call('HINCRBY', KEYS[2], 's:' .. ARGV[4] .. ':ci', 1)
  xadd(KEYS[3], ARGV[11], 'attempt.checked_in', ARGV[8], ARGV[9], at, ARGV[12])
end
redis.call('HSET', KEYS[1], 'dev', ARGV[2], 'ls', istr(now), 'ip', ARGV[5], 'ua', ARGV[6], 'meth', ARGV[7], 'sk', ARGV[10])
redis.call('HDEL', KEYS[1], 'asw')
if switched then
  xadd(KEYS[3], ARGV[11], 'attempt.device_switched', ARGV[8], ARGV[9], at, ARGV[13])
end
return {'ok', oldsk or '', state or ''}
