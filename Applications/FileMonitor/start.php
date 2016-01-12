<?php
use Workerman\Worker;
use Workerman\Lib\Timer;

// 默认监控Workerman的Applications目录
$monitor_dir = realpath(__DIR__.'/..');

// worker
$worker = new Worker();
// 名称，方便status时候辨别
$worker->name = 'FileMonitor';
// 该进程收到reload信号不执行reload
$worker->reloadable = false;
// 所有文件中最新的更改时间
$last_mtime = time();

// 进程启动后安装定时器
$worker->onWorkerStart = function()
{
    global $monitor_dir;
    // 只在debug模式下监控文件，守护进程模式不监控
    if(!Worker::$daemonize)
    {
        // 定时检查被监控目录文件是否有修改
        Timer::add(1, 'check_files_change', array($monitor_dir));
    }
};

// 检查文件是否有修改
function check_files_change($monitor_dir)
{
    global $last_mtime;
    // 递归遍历目录
    $dir_iterator = new RecursiveDirectoryIterator($monitor_dir);
    $iterator = new RecursiveIteratorIterator($dir_iterator);
    foreach ($iterator as $file)
    {
        // 只监控php文件
        if(pathinfo($file, PATHINFO_EXTENSION) != 'php')
        {
            continue;
        }
        // 在最近有修改
        if($last_mtime < $file->getMTime())
        {
            echo $file." update and reload\n";
            // 给父进程发送reload信号
            posix_kill(posix_getppid(), SIGUSR1);
            $last_mtime = $file->getMTime();
            break;
        }
    }
}