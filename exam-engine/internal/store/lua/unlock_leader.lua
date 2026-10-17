-- Release a leader lock held by ARGV[1]. KEYS: 1 lock
if redis.call('GET', KEYS[1]) == ARGV[1] then return redis.call('DEL', KEYS[1]) end
return 0
