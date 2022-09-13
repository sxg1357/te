<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/9/9
 * Time: 17:49
 */

// 多进程编写

// pcntl_fork 函数来创建一个子进程,系统调用是使用clone 来创建的
// 一、
// 1 ) 到底创建了几个进程？？？
// 2 ) 每个进程$count 是多少？？？
// 3 ) 每个进程到底从哪个地方开始运行代码的？？？
// 4 ) fork之后，每个进程的变量$i,$count 的值到底是多少 ？
// 5 ）每个进程运行到哪一行语句结束？
$count = 10;

for($i = 0; $i < 2; $i++) {
    $pid = pcntl_fork();
    if (0 == $pid) {
        $count += 1;
    } else {
        $count *= 10;
    }
}

while (true) {
    sleep(5);
    fprintf(STDOUT, "pid=%d,count=%d\n", posix_getpid(), $count);
}
// 分析
// 1) php demo11.php  开始运行
// 2)
// step1:遇到fork之后，创建了一个子进程，这个时候子进程我命名child 1 [$i=0;$count=10]
//    child 1 继续执行，这个时候满足$pid==0,$count+=1=11,$i++,$i=1;
// step2:
// cpu 调度到 parent process 要运行，$count=100,$i=1;
// step3:
// cpu 还在运行 parent process，fork又产生了一个子进程 child 2 [$i=1,$count=100;]
// cpu 还是在调度 parent process,$count=1000,$i=2,for循环退出
// 父进程的最终结果：$count=1000

// cpu 运行 child 2 子进程
// 执行$count+=1 $count=101,$i=2;
// child 2 子进程的最终结果是：$count=101

// cpu 又调度到 child 1 子进程[$i=1;$count=11]
// 这个时候child 1 执行 pcntl_fork函数 产生的子进程我命名 child 3 [$i=1;$count=11]
// child 1 继续执行 else $count=110,$i=2
// child 1的最终结果是:$count=110

// cpu 又调度到 child 3 子进程
// $count=12

// child 1  and child 2 是兄弟进程 的父进程就是当前的主进程
// child 3 的父亲是 child 1


//二、我加上break之后，到底有几个子进程，每个进程的$count又是多少？
//1 cpu 调度主进程，执行pcntl_fork之后，就产生了一个子进程，这个时候我命名为child 1 [$i=0;$count=10]
// cpu 继续执行主进程，$count=100,$i=0;这个时候退出for循环
// 主进程的最终结果：$count=100;

//2 cpu 又调度到child 1 子进程，执行if分支，$count=11;$i=1;
// 继续运行该进程，执行pcntl_fork函数，又创建了一个子进程，命名child 2[$i=1;$count=11]
// cpu还是继续调度child 1 子进程，执行else分支，$count=110,child1遇到break退出
// child1的最终结果就是110

//3 cpu又调度到child 2子进程，执行if分支，$count=12,$i=2;

// child 1子进程的父亲是主进程
// child 2子进程父亲就是child1


