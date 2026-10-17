-- Emit attempt.snapshot for writing attempts whose answers changed, at most every interval.
-- KEYS: 1 events
-- ARGV: 1 now, 2 interval_ms, 3 run, 4 maxlen, 5 attempt key prefix, 6.. reg ids
local now, iv = tonumber(ARGV[1]), tonumber(ARGV[2])
local c = 0
for i = 6, #ARGV do
  local k = ARGV[5] .. ARGV[i]
  local f = redis.call('HMGET', k, 'state', 'rev', 'snr', 'sna', 'ans')
  if f[1] == 'writing' then
    local rev = num(f[2])
    if rev > num(f[3]) and now - num(f[4]) >= iv then
      xadd(KEYS[1], ARGV[4], 'attempt.snapshot', ARGV[3], ARGV[i], iso(now),
        '{"rev":' .. istr(rev) .. ',"answers":' .. (f[5] or '{}') .. '}')
      redis.call('HSET', k, 'snr', istr(rev), 'sna', istr(now))
      c = c + 1
    end
  end
end
return c
