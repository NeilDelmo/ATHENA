<?php

$group = trim(explode(':', trim(file_get_contents('/proc/self/cgroup')), 3)[2]);
$root = '/sys/fs/cgroup'.$group;
$limit = trim(file_get_contents($root.'/memory.max'));
$swap = trim(file_get_contents($root.'/memory.swap.max'));
$cpu = trim(file_get_contents($root.'/cpu.max'));
if ($limit !== (string) (1792 * 1024 * 1024) || $swap !== '0') {
    throw new RuntimeException('The requested memory and swap limits were not applied.');
}
$allocation = str_repeat('x', 64 * 1024 * 1024);
echo json_encode([
    'php' => PHP_VERSION,
    'memory_limit_bytes' => $limit,
    'swap_limit_bytes' => $swap,
    'cpu_max' => $cpu,
    'allocated_bytes' => strlen($allocation),
    'memory_current_bytes' => trim(file_get_contents($root.'/memory.current')),
    'cgroup' => $group,
], JSON_PRETTY_PRINT).PHP_EOL;
