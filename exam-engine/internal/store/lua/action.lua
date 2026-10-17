-- Coordinator actions: extend | lock | unlock | allow_switch | event (emit only). Always emits attempt.action.
-- KEYS: 1 att, 2 agg, 3 events, 4 dlz, 5 extra
-- ARGV: 1 now, 2 action, 3 minutes, 4 run, 5 reg, 6 maxlen, 7 event data, 8 base_ms (0 = individual), 9 duration_ms
local now = tonumber(ARGV[1])
local f = redis.call('HMGET', KEYS[1], 'state', 'lk', 'sa')
local state, act = f[1], ARGV[2]
if act == 'extend' then
  if state == 'submitted' then return {'submitted'} end
  local ex = redis.call('HINCRBY', KEYS[5], ARGV[5], tonumber(ARGV[3]))
  if ex > num(redis.call('HGET', KEYS[2], 'mx')) then redis.call('HSET', KEYS[2], 'mx', istr(ex)) end
  if state == 'writing' then
    local base = tonumber(ARGV[8])
    if base == 0 then base = num(f[3]) end
    local dl = base + tonumber(ARGV[9]) + ex * 60000
    redis.call('HSET', KEYS[1], 'dl', istr(dl))
    redis.call('ZADD', KEYS[4], istr(dl), ARGV[5])
  end
elseif act == 'lock' then
  if state == 'submitted' then return {'submitted'} end
  if f[2] ~= '1' then
    redis.call('HSET', KEYS[1], 'lk', '1')
    redis.call('HINCRBY', KEYS[2], 'lk', 1)
  end
elseif act == 'unlock' then
  if f[2] == '1' then
    redis.call('HSET', KEYS[1], 'lk', '0')
    redis.call('HINCRBY', KEYS[2], 'lk', -1)
  end
elseif act == 'allow_switch' then
  redis.call('HSET', KEYS[1], 'asw', '1')
end
xadd(KEYS[3], ARGV[6], 'attempt.action', ARGV[4], ARGV[5], iso(now), ARGV[7])
return {'ok'}
