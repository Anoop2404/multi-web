-- Remove one roster entry and its index entries.
-- KEYS: 1 roster, 2 idx:user, 3 idx:ticket, 4 agg, 5 extra
-- ARGV: 1 reg
local reg = ARGV[1]
local old = redis.call('HGET', KEYS[1], reg)
if not old then return 0 end
local o = cjson.decode(old)
for _, k in ipairs({o.username or '', o.email or ''}) do
  if k ~= '' and redis.call('HGET', KEYS[2], k) == reg then redis.call('HDEL', KEYS[2], k) end
end
local t = o.hall_ticket or ''
if t ~= '' then
  local cur = redis.call('HGET', KEYS[3], t)
  if cur then
    local out = {}
    for _, v in ipairs(cjson.decode(cur)) do
      if tostring(v) ~= reg then table.insert(out, v) end
    end
    if #out == 0 then redis.call('HDEL', KEYS[3], t) else redis.call('HSET', KEYS[3], t, cjson.encode(out)) end
  end
end
redis.call('HDEL', KEYS[1], reg)
redis.call('HDEL', KEYS[5], reg)
redis.call('HINCRBY', KEYS[4], 'r', -1)
redis.call('HINCRBY', KEYS[4], 's:' .. (o.school_id or '') .. ':r', -1)
redis.call('HINCRBY', KEYS[4], 'rv', 1)
return 1
