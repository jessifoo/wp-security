<?php
/**
 * OMS Rate Limiter Class
 */
class OMS_Rate_Limiter {
    private $rate_limits;
    private $last_request_time;

    public function __construct(array $rate_limits) {
        $this->rate_limits = $rate_limits;
        $this->last_request_time = [];
    }

    public function throttle($key = 'default') {
        $current_time = microtime(true);
        if (!isset($this->last_request_time[$key])) {
            $this->last_request_time[$key] = $current_time;
            return;
        }

        $elapsed = $current_time - $this->last_request_time[$key];
        $limit = $this->rate_limits[$key] ?? 1;

        if ($elapsed < $limit) {
            usleep(($limit - $elapsed) * 1000000);
        }

        $this->last_request_time[$key] = microtime(true);
    }
}
