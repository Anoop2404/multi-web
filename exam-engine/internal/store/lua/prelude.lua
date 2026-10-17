-- Shared helpers, prepended to every script.
local function iso(ms)
  local s = math.floor(ms / 1000)
  local days = math.floor(s / 86400)
  local rem = s - days * 86400
  local h = math.floor(rem / 3600)
  local mi = math.floor((rem - h * 3600) / 60)
  local se = rem - h * 3600 - mi * 60
  local z = days + 719468
  local era = math.floor(z / 146097)
  local doe = z - era * 146097
  local yoe = math.floor((doe - math.floor(doe / 1460) + math.floor(doe / 36524) - math.floor(doe / 146096)) / 365)
  local y = yoe + era * 400
  local doy = doe - (365 * yoe + math.floor(yoe / 4) - math.floor(yoe / 100))
  local mp = math.floor((5 * doy + 2) / 153)
  local d = doy - math.floor((153 * mp + 2) / 5) + 1
  local m
  if mp < 10 then m = mp + 3 else m = mp - 9 end
  if m <= 2 then y = y + 1 end
  return string.format('%04d-%02d-%02dT%02d:%02d:%02dZ', y, m, d, h, mi, se)
end

local function num(v)
  return tonumber(v) or 0
end

local function istr(n)
  return string.format('%d', n)
end

-- No MAXLEN by default: unconsumed events are never dropped. The sweeper leader trims only
-- delivered+acknowledged entries (XTRIM MINID). maxlen > 0 is the optional hard safety cap.
local function xadd(stream, maxlen, typ, run, reg, at, data)
  if (tonumber(maxlen) or 0) > 0 then
    return redis.call('XADD', stream, 'MAXLEN', '~', maxlen, '*', 'type', typ, 'run', run, 'reg_id', reg, 'at', at, 'data', data)
  end
  return redis.call('XADD', stream, '*', 'type', typ, 'run', run, 'reg_id', reg, 'at', at, 'data', data)
end

-- Run-level controls from the ctl hash: extra minutes for everyone (POST .../extend) and hold.
-- Stored attempt deadlines exclude the run-level extra; the effective deadline adds it.
local function runctl(ctl)
  local c = redis.call('HMGET', ctl, 'xm', 'hold')
  return num(c[1]) * 60000, c[2] == '1'
end

local EMPTY_CKS = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855'
