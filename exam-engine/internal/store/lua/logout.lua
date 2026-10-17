-- Release the device binding if this session's device still holds it.
-- KEYS: 1 att; ARGV: 1 dev
if redis.call('HGET', KEYS[1], 'dev') == ARGV[1] then
  redis.call('HSET', KEYS[1], 'dev', '', 'sk', '')
  return 1
end
return 0
