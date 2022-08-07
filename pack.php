<?php
/**
 * Created by PhpStorm.
 * User: sxg
 * Date: 2022/8/7
 * Time: 11:13
 */

//$bin = pack("a9", "中国人");
//print_r(unpack("a9", $bin));


//$bin = pack('c', 'a');  c有符号位一个字节范围
//echo $bin;

$bin = pack("n", 368);   //0000 0000 0000 0000 0000 0001 0111 0000   n两个字节无符号位 大端字节序  v小端字节序 存储顺序相反
//print_r(unpack("V", $bin));
$ret = 0;
$ret |= ord($bin[0]) << 8;
$ret |= ord($bin[1]) << 0;
print_r($ret);

echo "**********************************\r\n";

$x = 65539;    //0000 0001 0000 0000 0000 0011
//$bin1 = pack("N",$a1);  //4bytes
//print_r(unpack("Nlen",$bin1));

$a1 = $x >> 16 & 0xFF;  //0000 0001  1
$a2 = $x >> 8 & 0xFF;   //0000 0000  0
$a3 = $x >> 0 & 0xFF;   //0000 0011  3

$ret = pack("ccc", $a1, $a2, $a3);
echo "$a1-$a2-$a3\r\n";
$zet = unpack("ca1/ca2/ca3", $ret);

$ret = 0;
$ret |= $zet['a1'] << 16;
$ret |= $zet['a2'] << 8;
$ret |= $zet['a3'] << 0;
echo "$ret\r\n";