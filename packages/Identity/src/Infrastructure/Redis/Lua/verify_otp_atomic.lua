-- ==============================================================================
-- FLASHCOMMERCE STAGE 3 WRITE-DEFERRED VERIFICATION ATOMIC SCRIPT
-- ==============================================================================
-- KEYS[1]: email_otp:{userId}
-- KEYS[2]: email_otp_attempts:{userId}
-- KEYS[3]: email_otp_cooldown:{userId}
-- KEYS[4]: user_verified:{userId}
-- KEYS[5]: stream:user_verified
-- ARGV[1]: input_otp
-- ARGV[2]: max_attempts (5)
-- ARGV[3]: verified_cache_ttl (86400)
-- ARGV[4]: stream_payload_json

local stored_otp = redis.call('GET', KEYS[1])
local attempts = tonumber(redis.call('GET', KEYS[2]) or "0")
local max_attempts = tonumber(ARGV[2])

-- 1. Circuit Breaker Check
if attempts >= max_attempts then
    return -1 -- Locked out
end

-- 2. OTP Expiry or Missing Check
if not stored_otp then
    return -2 -- Expired or not found
end

-- 3. OTP Code Validation
if stored_otp ~= ARGV[1] then
    local new_attempts = redis.call('INCR', KEYS[2])
    redis.call('EXPIRE', KEYS[2], 900)
    if new_attempts >= max_attempts then
        redis.call('DEL', KEYS[1]) -- Evict OTP key on circuit breaker trip
        return -1
    end
    return 0 -- Invalid OTP
end

-- 4. Valid OTP: Atomic Single-Hop State Transitions
redis.call('DEL', KEYS[1])
redis.call('DEL', KEYS[2])
redis.call('DEL', KEYS[3])
redis.call('SETEX', KEYS[4], tonumber(ARGV[3]), "1")
redis.call('XADD', KEYS[5], '*', 'payload', ARGV[4])

return 1 -- Verification Succeeded
