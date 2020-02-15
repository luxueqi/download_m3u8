<?php

class Down {

	private static $host;

	private static $urldirname;

	private static function cdir($path) {
		if (!preg_match('/^(https?:\/\/)([^\/]+)(\/.+)\/(.+\..+)$/', $path, $match)) {
			die('链接错误' . PHP_EOL);
		}

		self::$host = $match[1] . $match[2];

		self::$urldirname = $match[1] . $match[2] . $match[3];

		$path = $match[2] . str_replace('/', '-', $match[3]);
		//var_dump($path);exit;
		if (!is_dir($path)) {
			if (mkdir($path, 0777)) {
				return $path;
			} else {
				throw new Exception("目录创建错误");
			}
		}
		return $path;

	}

	public static function run() {

		$cliparams = getopt('s:c:u:');

		$threadCount = isset($cliparams['c']) ? $cliparams['c'] : 4;

		$url = $cliparams['u'];

		//var_dump($cliparams);exit;

		$path = self::cdir($url);

		$content = file_get_contents($url);

		if (strpos($content, '#EXTM3U') === false) {
			die('非M3U8的链接');
		}
		$keys = '';
		if (preg_match('/EXT-X-KEY:METHOD=([^,]+),URI="([^,]+)"/', $content, $klist)) {
			echo "Decode Method:" . $klist[1] . PHP_EOL;

			$keys = file_get_contents(self::$urldirname . '/' . $klist[2]);

			if (empty($keys)) {
				die('key获取错误' . PHP_EOL);
			}

			echo "key:" . $keys . PHP_EOL;
		}

		if (preg_match_all('/#EXTINF:.+,\n(.+\.ts)\n/', $content, $mslist)) {

			$mslist = $mslist[1];

			$workcount = count($mslist);

			$start = (isset($cliparams['s']) && $cliparams['s'] < $workcount) ? $cliparams['s'] : 0;

			echo "work:" . $workcount . PHP_EOL . 'start:' . PHP_EOL;

			for ($i = $start; $i < $workcount; $i += $threadCount) {
				$tsname = pathinfo($mslist[$i])['basename'];
				$tsurl = '';
				if (strpos($mslist[$i], '/') === 0) {
					$tsurl = self::$host . $mslist[$i];
				} elseif (strpos($mslist[$i], 'http') === 0) {
					$tsurl = $mslist[$i];
				} else {
					$tsurl = self::$urldirname . '/' . $tsname;
				}

				echo "thread-{$start}-" . ($i + 1) . "/{$workcount}.{$tsurl}" . PHP_EOL;
				$tspath = $path . '/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '-' . $tsname;
				if (file_exists($tspath)) {
					echo "已存在" . PHP_EOL;
					continue;
				}
				//die($tspath);
				echo "正在下载..." . ($i + 1) . PHP_EOL;
				//exit;
				$tscontent = @file_get_contents($tsurl);

				if ($keys != '' && $tscontent != '') {

					$tscontent = openssl_decrypt($tscontent, 'AES-128-CBC', $keys, OPENSSL_RAW_DATA, $keys);

				}
				if (empty($tscontent)) {
					//throw new Exception("获取错误");
					echo ('获取错误，重新获取' . PHP_EOL);
					$i = $i - $threadCount;
					//$ind--;
					continue;

				}
				file_put_contents($tspath, $tscontent);
			}

		}
		//exit;

		echo "下载完成:thread-{$start}" . PHP_EOL;
		if (count(scandir($path)) - 2 == $workcount) {
			exec("cat {$path}/*.ts >{$path}/new.mp4");
			exec("rm -f {$path}/*.ts");
			//exec("mv {$path}/new.tmp {$path}/new.mp4");

			echo "合并完成" . PHP_EOL;
		}

	}
}

Down::run();

?>