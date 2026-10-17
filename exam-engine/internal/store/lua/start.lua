-- Start writing: set the deadline, release the key (by state), emit attempt.started.
-- KEYS: 1 att, 2 agg, 3 events, 4 dlz, 5 seen, 6 extra, 7 ctl
-- ARGV: 1 now, 2 dev, 3 base_ms (0 = individual timing: now), 4 duration_ms, 5 run, 6 reg, 7 maxlen
local now = tonumber(ARGV[1])
local f = redis.call('HMGET', KEYS[1], 'state', 'dev', 'lk', 'sid', 'dl')
if not f[1] then return {'gone', 0} end
if f[2] ~= ARGV[2] then return {'revoked', 0} end
local rx = runctl(KEYS[7])
if f[1] == 'submitted' then return {'submitted', num(f[5]) + rx} end
if f[1] == 'writing' then return {'writing', num(f[5]) + rx} end
if f[3] == '1' then return {'locked', 0} end
local extra = num(redis.call('HGET', KEYS[6], ARGV[6]))
local base = tonumber(ARGV[3])
if base == 0 then base = now end
local dl = base + tonumber(ARGV[4]) + extra * 60000
if dl + rx <= now then return {'closed', dl + rx} end
redis.call('HSET', KEYS[1], 'state', 'writing', 'sa', istr(now), 'dl', istr(dl), 'ls', istr(now), 'sna', istr(now), 'snr', '0')
redis.call('ZADD', KEYS[4], istr(dl), ARGV[6])
redis.call('ZADD', KEYS[5], istr(now), ARGV[6])
redis.call('HINCRBY', KEYS[2], 'st', 1)
redis.call('HINCRBY', KEYS[2], 's:' .. (f[4] or '') .. ':st', 1)
xadd(KEYS[3], ARGV[7], 'attempt.started', ARGV[5], ARGV[6], iso(now), '{"deadline":"' .. iso(dl + rx) .. '"}')
return {'started', dl + rx}
