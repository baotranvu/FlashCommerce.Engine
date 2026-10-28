-- KEYS[1]: flash_sale:stock:{product_id}
-- KEYS[2]: inventory:hold:{hold_token}

local stock_key = KEYS[1]
local hold_key = KEYS[2]

local qty = tonumber(redis.call('hget', hold_key, 'qty') or 0)

if qty > 0 then
    redis.call('incrby', stock_key, qty)
    redis.call('del', hold_key)
    return {1, "RELEASED"}
end

return {0, "HOLD_NOT_FOUND_OR_EXPIRED"}
