<?php
return [
    // Maximum concurrent active downloads per user before denying (0 = unlimited)
    'concurrent_limit_per_user' => 0,
    // When global active downloads exceed this, switch to throttled mode
    'global_concurrent_threshold' => 10,
    // Chunk size in bytes for throttled/manual read
    'base_chunk_size' => 16384, // 16 KB
    // Delay (ms) between chunks in normal mode (0 = no delay)
    'normal_delay_ms' => 0,
    // Delay (ms) between chunks when throttled
    'throttled_delay_ms' => 35, // ~ 16KB per 35ms ≈ 450KB/s per connection
    // Optional hard cap KB/s (overrides delay calc if set >0)
    'hard_cap_kbps' => 0,
];
