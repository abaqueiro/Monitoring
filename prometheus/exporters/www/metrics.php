<?php

$t1 = microtime();

function microtime_to_double($t){
	$A = explode(' ',$t);
	return $A[0] + $A[1];
}

$METRIC_PREFIX = 'm_';

$m_type = [];
$m_A = [];

header('Content-Type: text/plain; version=0.0.4');

# load metrics
$fname = '/proc/loadavg';
$data = trim( file_get_contents($fname) );
$A = explode(' ',$data);
$m_A['load'] = $A[0];
$m_A['load_5'] = $A[1];
$m_A['load_15'] = $A[2];
$m_A['last_pid'] = $A[4];
$A = explode('/',$A[3]);
$m_A['process_count'] = $A[1];

# cpu metrics
$fname = '/proc/stat';
$A = file($fname);
$A = explode(' ', trim($A[0]) );
$m_A['cpu_user'] = $A[2];
$m_A['cpu_nice'] = $A[3];
$m_A['cpu_system'] = $A[4];
$m_A['cpu_idle'] = $A[5];
$m_A['cpu_iowait'] = $A[6];
$m_A['cpu_irq'] = $A[7];
$m_A['cpu_softirq'] = $A[8];
$m_A['cpu_steal'] = $A[9];
$m_A['cpu_guest'] = $A[10];
$m_A['cpu_guest_nice'] = $A[11];
$m_type['cpu_user'] = 'counter';
$m_type['cpu_nice'] = 'counter';
$m_type['cpu_system'] = 'counter';
$m_type['cpu_idle'] = 'counter';
$m_type['cpu_iowait'] = 'counter';
$m_type['cpu_irq'] = 'counter';
$m_type['cpu_softirq'] = 'counter';
$m_type['cpu_steal'] = 'counter';
$m_type['cpu_guest'] = 'counter';
$m_type['cpu_guest_nice'] = 'counter';

# ram metrics
$fname = '/proc/meminfo';
$A = file($fname);
$B = [];
foreach( $A as $line){
	$line = trim($line);
	$p = explode(':',$line);
	$name = trim($p[0]);
	$q = trim($p[1]);
	$q = explode(' ',$q);
	$value = $q[0];
	$B[ $name ] = $value;
}
// used = total-free-buff-cache
// TODO, check index exists
$m_A['mem_total'] = $B['MemTotal'];
$m_A['mem_free'] = $B['MemFree'];
$m_A['mem_available'] = $B['MemAvailable'];
$m_A['mem_buffers'] = $B['Buffers'];
$m_A['mem_cache'] = $B['Cached'];
$m_A['mem_shared'] = $B['Shmem'];
$m_A['mem_swap_total'] = $B['SwapTotal'];
$m_A['mem_swap_free'] = $B['SwapFree'];
$m_A['mem_active'] = $B['Active'];
$m_A['mem_inactive'] = $B['Inactive'];

# network metrics
$m_type[ "net_rx_bytes" ] = 'counter';
$m_type[ "net_rx_packets" ] = 'counter';
$m_type[ "net_rx_errors" ] = 'counter';
$m_type[ "net_rx_drops" ] = 'counter';
$m_type[ "net_tx_bytes" ] = 'counter';
$m_type[ "net_tx_packets" ] = 'counter';
$m_type[ "net_tx_errors" ] = 'counter';
$m_type[ "net_tx_drops" ] = 'counter';
$fname = '/proc/net/dev';
$A = file($fname);
$nl = count($A);
for($i=2; $i<$nl; $i++){
        $line = trim($A[$i]);
        $B = preg_split('/[\s]+/',$line);
        $if_name = substr($B[0],0,-1);
        $m_A[ "net_rx_bytes{if=\"$if_name\"}" ] = $B[1];
        $m_A[ "net_rx_packets{if=\"$if_name\"}" ] = $B[2];
        $m_A[ "net_rx_errors{if=\"$if_name\"}" ] = $B[3];
        $m_A[ "net_rx_drops{if=\"$if_name\"}" ] = $B[4];
        $m_A[ "net_tx_bytes{if=\"$if_name\"}" ] = $B[9];
        $m_A[ "net_tx_packets{if=\"$if_name\"}" ] = $B[10];
        $m_A[ "net_tx_errors{if=\"$if_name\"}" ] = $B[11];
        $m_A[ "net_tx_drops{if=\"$if_name\"}" ] = $B[12];
}

# storage
$cmd = "df -k";
$out = shell_exec($cmd);
$A = explode("\n",$out);
$nl = count($A);
for($i=1; $i<$nl; $i++){
	$line = trim($A[$i]);
	if (strlen($line) == 0 )
		continue;

	$B = preg_split('/[\s]+/',$A[$i]);
	$dev = $B[0];
	$dev_size = $B[1];
	$dev_used = $B[2];
	$dev_available = $B[3];
	$dev_p_use = $B[4];
	$dev_mount = $B[5];

	if ( strlen($dev) == 0 )
		continue;
	if ( $dev == 'tmpfs' && $dev_mount != '/dev/shm' )
		continue;

	if ( $dev_mount == '/dev/shm' )
		$dev = $dev_mount;

	$m_name = "fs_used{dev=\"$dev\"}";
	$m_A[ $m_name ] = $dev_used;

	$m_name = "fs_available{dev=\"$dev\"}";
	$m_A[ $m_name ] = $dev_available;
}

# specific
$dname = '/home/monitor/prometheus/data';
if ( file_exists( $dname ) ){
	$cmd = "du -s $dname";
	$out = trim(shell_exec($cmd));
	$A = explode("\t", $out);
	$m_A['du_prom_data'] = $A[0];
}

# output metrics
foreach( $m_A as $m_key => $m_val ){
	$normal_key = $m_key; // key without tags inside {}
	$pos = strpos($m_key,'{');
	if ($pos === false){
		$normal_key = $m_key;
	} else {
		$normal_key = substr($m_key,0,$pos);
	}
	$type = isset($m_type[$normal_key])? $m_type[$normal_key] : 'gauge';
	$normal_name = $METRIC_PREFIX . $normal_key;
	$name = $METRIC_PREFIX . $m_key;
	echo "# TYPE $normal_name $type\n";
	echo "$name $m_val\n";
}

# exporter self metrics
echo "# TYPE exporter_runtime gauge\n";
$t2 = microtime();
echo "exporter_runtime " . microtime_to_double($t2)-microtime_to_double($t1) . "\n";

?>
