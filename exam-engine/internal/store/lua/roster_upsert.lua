-- Upsert roster entries and keep the login indexes, per-school counters and extra time in step.
-- KEYS: 1 roster, 2 idx:user, 3 idx:ticket, 4 agg, 5 extra, 6 schools, 7 dlz
-- ARGV: 1 attempt key prefix, 2 base_ms (0 = individual), 3 duration_ms,
--       then groups of 8: reg, json, school_id, school_name, hall_ticket, username, email, extra_minutes
local function tadd(t, reg)
  if t == '' then return end
  local cur = redis.call('HGET', KEYS[3], t)
  local list = {}
  if cur then list = cjson.decode(cur) end
  for _, v in ipairs(list) do
    if tostring(v) == reg then return end
  end
  table.insert(list, tonumber(reg))
  redis.call('HSET', KEYS[3], t, cjson.encode(list))
end
local function tdel(t, reg)
  if t == '' then return end
  local cur = redis.call('HGET', KEYS[3], t)
  if not cur then return end
  local out = {}
  for _, v in ipairs(cjson.decode(cur)) do
    if tostring(v) ~= reg then table.insert(out, v) end
  end
  if #out == 0 then redis.call('HDEL', KEYS[3], t) else redis.call('HSET', KEYS[3], t, cjson.encode(out)) end
end
local function idel(k, reg)
  if k ~= '' and redis.call('HGET', KEYS[2], k) == reg then redis.call('HDEL', KEYS[2], k) end
end
local base0, dur = tonumber(ARGV[2]), tonumber(ARGV[3])
local mx = num(redis.call('HGET', KEYS[4], 'mx'))
local n = 0
for i = 4, #ARGV, 8 do
  local reg, js, sid, sname, ticket, user, email, extra =
    ARGV[i], ARGV[i + 1], ARGV[i + 2], ARGV[i + 3], ARGV[i + 4], ARGV[i + 5], ARGV[i + 6], tonumber(ARGV[i + 7])
  local old = redis.call('HGET', KEYS[1], reg)
  if old then
    local o = cjson.decode(old)
    local osid = o.school_id or ''
    if (o.username or '') ~= user then idel(o.username or '', reg) end
    if (o.email or '') ~= email then idel(o.email or '', reg) end
    if (o.hall_ticket or '') ~= ticket then tdel(o.hall_ticket or '', reg) end
    if osid ~= sid then
      redis.call('HINCRBY', KEYS[4], 's:' .. osid .. ':r', -1)
      redis.call('HINCRBY', KEYS[4], 's:' .. sid .. ':r', 1)
    end
  else
    redis.call('HINCRBY', KEYS[4], 'r', 1)
    redis.call('HINCRBY', KEYS[4], 's:' .. sid .. ':r', 1)
  end
  redis.call('HSET', KEYS[1], reg, js)
  if user ~= '' then redis.call('HSET', KEYS[2], user, reg) end
  if email ~= '' then redis.call('HSET', KEYS[2], email, reg) end
  tadd(ticket, reg)
  redis.call('HSET', KEYS[6], sid, sname)
  local cur = redis.call('HGET', KEYS[5], reg)
  if (not cur) or tonumber(cur) ~= extra then
    redis.call('HSET', KEYS[5], reg, istr(extra))
    local akey = ARGV[1] .. reg
    local a = redis.call('HMGET', akey, 'state', 'sa')
    if a[1] == 'writing' then
      local base = base0
      if base == 0 then base = num(a[2]) end
      local dl = base + dur + extra * 60000
      redis.call('HSET', akey, 'dl', istr(dl))
      redis.call('ZADD', KEYS[7], istr(dl), reg)
    end
  end
  if extra > mx then
    mx = extra
    redis.call('HSET', KEYS[4], 'mx', istr(mx))
  end
  n = n + 1
end
redis.call('HINCRBY', KEYS[4], 'rv', 1)
return n
