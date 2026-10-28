-- KEYS[1]: flash_sale:stock:{product_id}
-- KEYS[2]: inventory:hold:{hold_token}
-- ARGV[1]: requested_quantity
-- ARGV[2]: ttl_seconds (e.g. 300)
-- ARGV[3]: user_id

local stock_key = KEYS[1]
local hold_key = KEYS[2]
local req_qty = tonumber(ARGV[1])
local ttl = tonumber(ARGV[2])
local user_id = ARGV[3]

local current_stock = tonumber(redis.call('get', stock_key) or 0)

if current_stock < req_qty then
    return {0, current_stock, "OUT_OF_STOCK"}
end

-- Atomic reservation in RAM
redis.call('decrby', stock_key, req_qty)
redis.call('hset', hold_key, 'qty', req_qty, 'user_id', user_id, 'status', 'HELD')
redis.call('expire', hold_key, ttl)

local remaining_stock = current_stock - req_qty
return {1, remaining_stock, "RESERVED"}
