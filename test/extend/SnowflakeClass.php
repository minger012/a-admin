<?php

class SnowflakeClass
{
    // 时间戳基准 (2010-11-04 09:42:54 UTC)
    const EPOCH = 1288834974657;

    // 位分配
    const TIMESTAMP_BITS = 41;
    const DATACENTER_BITS = 5;
    const WORKER_BITS = 5;
    const SEQUENCE_BITS = 12;

    // 最大取值范围
    const MAX_DATACENTER_ID = -1 ^ (-1 << self::DATACENTER_BITS);
    const MAX_WORKER_ID = -1 ^ (-1 << self::WORKER_BITS);
    const MAX_SEQUENCE = -1 ^ (-1 << self::SEQUENCE_BITS);

    // 移位量
    const WORKER_SHIFT = self::SEQUENCE_BITS;
    const DATACENTER_SHIFT = self::SEQUENCE_BITS + self::WORKER_BITS;
    const TIMESTAMP_SHIFT = self::SEQUENCE_BITS + self::WORKER_BITS + self::DATACENTER_BITS;

    protected $datacenterId;
    protected $workerId;
    protected $sequence = 0;
    protected $lastTimestamp = -1;

    public function __construct($datacenterId = 0, $workerId = 0)
    {
        if ($datacenterId > self::MAX_DATACENTER_ID || $datacenterId < 0) {
            throw new InvalidArgumentException("Datacenter ID must be between 0 and " . self::MAX_DATACENTER_ID);
        }

        if ($workerId > self::MAX_WORKER_ID || $workerId < 0) {
            throw new InvalidArgumentException("Worker ID must be between 0 and " . self::MAX_WORKER_ID);
        }

        $this->datacenterId = $datacenterId;
        $this->workerId = $workerId;
    }

    public function nextId()
    {
        $timestamp = $this->timestamp();

        if ($timestamp < $this->lastTimestamp) {
            // 时钟回拨处理
            throw new RuntimeException("Clock moved backwards. Refusing to generate id for " . ($this->lastTimestamp - $timestamp) . " milliseconds");
        }

        if ($this->lastTimestamp == $timestamp) {
            $this->sequence = ($this->sequence + 1) & self::MAX_SEQUENCE;
            if ($this->sequence == 0) {
                // 同一毫秒内序列号用尽，等待下一毫秒
                $timestamp = $this->tilNextMillis($this->lastTimestamp);
            }
        } else {
            $this->sequence = 0;
        }

        $this->lastTimestamp = $timestamp;

        // 组合生成 ID
        return (($timestamp - self::EPOCH) << self::TIMESTAMP_SHIFT) |
            ($this->datacenterId << self::DATACENTER_SHIFT) |
            ($this->workerId << self::WORKER_SHIFT) |
            $this->sequence;
    }

    protected function timestamp()
    {
        return (int)(microtime(true) * 1000);
    }

    protected function tilNextMillis($lastTimestamp)
    {
        $timestamp = $this->timestamp();
        while ($timestamp <= $lastTimestamp) {
            usleep(1000); // 睡眠 1 毫秒
            $timestamp = $this->timestamp();
        }
        return $timestamp;
    }

    /**
     * 解析 ID 为各部分信息（用于调试）
     */
    public static function parseId($id)
    {
        $binary = str_pad(decbin($id), 64, '0', STR_PAD_LEFT);

        return [
            'timestamp' => ($id >> self::TIMESTAMP_SHIFT) + self::EPOCH,
            'datacenter_id' => ($id >> self::DATACENTER_SHIFT) & self::MAX_DATACENTER_ID,
            'worker_id' => ($id >> self::WORKER_SHIFT) & self::MAX_WORKER_ID,
            'sequence' => $id & self::MAX_SEQUENCE,
            'binary' => $binary,
        ];
    }
}