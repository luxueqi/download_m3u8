<?php

/**
 *
 */
class Down {
	//private static $threadCount;

	private static $host;

	private static $urldirname;

	private static $worklist;

	private static $workCount;

	private static $threadCount;

	private static $path;

	private static $keys;

	private static $isfork;

	private static $worksarr = [];

	private static function cdir($path) {
		if (!preg_match('/^(https?:\/\/)([^\/]+)(\/.+)\/(.+\..+)$/', $path, $match)) {
			die('链接错误' . PHP_EOL);
		}

		self::$host = $match[1] . $match[2];

		self::$urldirname = $match[1] . $match[2] . $match[3];

		self::$path = $match[2] . str_replace('/', '-', $match[3]);
		//var_dump($path);exit;
		if (!is_dir(self::$path)) {
			if (!mkdir(self::$path, 0777)) {
				throw new Exception("目录创建错误");
			}
		}
		//return true;

	}

	private static function init($url, $threadCount) {
		self::cdir($url);

		self::$isfork = function_exists('pcntl_fork');

		self::$threadCount = $threadCount;

		$content = file_get_contents($url);

		if (strpos($content, '#EXTM3U') === false) {
			die('非M3U8的链接');
		}

		if (preg_match('/EXT-X-KEY:METHOD=([^,]+),URI="([^,]+)"/', $content, $klist)) {
			echo "Decode Method:" . $klist[1] . PHP_EOL;

			self::$keys = file_get_contents(self::$urldirname . '/' . $klist[2]);

			if (empty(self::$keys)) {
				die('key获取错误' . PHP_EOL);
			}

			echo "key:" . self::$keys . PHP_EOL;
		}

		if (preg_match_all('/#EXTINF:.+,\n(.+\.ts)\n/', $content, $mslist)) {

			self::$worklist = $mslist[1];

			self::$workCount = count(self::$worklist);

		} else {
			die('没有数据' . PHP_EOL);
		}

	}

	private static function work($start = 0) {

		for ($i = $start; $i < self::$workCount; $i += self::$threadCount) {

			$tsname = pathinfo(self::$worklist[$i])['basename'];
			$tsurl = '';
			if (strpos(self::$worklist[$i], '/') === 0) {
				$tsurl = self::$host . self::$worklist[$i];
			} elseif (strpos(self::$worklist[$i], 'http') === 0) {
				$tsurl = self::$worklist[$i];
			} else {
				$tsurl = self::$urldirname . '/' . $tsname;
			}

			echo "thread-{$start}-" . ($i + 1) . "/" . self::$workCount . ".{$tsurl}" . PHP_EOL;
			$tspath = self::$path . '/' . str_pad($i + 1, 5, '0', STR_PAD_LEFT) . '-' . $tsname;
			if (file_exists($tspath)) {
				echo "已存在" . PHP_EOL;
				continue;
			}
			//die($tspath);
			echo "正在下载..." . ($i + 1) . PHP_EOL;
			//exit;
			$tscontent = @file_get_contents($tsurl);

			if (!empty(self::$keys) && $tscontent != '') {

				$tscontent = openssl_decrypt($tscontent, 'AES-128-CBC', self::$keys, OPENSSL_RAW_DATA, self::$keys);

			}
			if (empty($tscontent)) {
				//throw new Exception("获取错误");
				echo ('获取错误，重新获取' . PHP_EOL);
				$i = $i - self::$threadCount;
				//$ind--;
				continue;

			}
			file_put_contents($tspath, $tscontent);
		}

		//echo "任务thread-{$start}完成" . PHP_EOL;

	}

	private static function createFork() {
		for ($i = 0; $i < self::$threadCount; $i++) {

			$pid = pcntl_fork();

			if ($pid == -1) {
				die('进程创建失败');
			} elseif ($pid) {
				//pcntl_wait($status, WNOHANG);
				//pcntl_wait($status, WNOHANG);
				self::$worksarr[$pid] = $i;
				//var_dump($worksarr);

			} else {

				self::work($i);

				exit(0);
			}

		}
		//self::endFork();

	}

	private static function endFork() {

		while (!empty(self::$worksarr)) {
			$pid = pcntl_wait($status, WNOHANG);

			if ($pid > 0) {

				echo "进程-{$pid}-任务thread-" . self::$worksarr[$pid] . "完成" . PHP_EOL;
				unset(self::$worksarr[$pid]);
			}

		}
	}

	private static function createWork() {
		if (self::$isfork) {
			self::createFork();

		} else {
			self::$threadCount = 1;
			self::work();
		}
	}

	private static function catFile() {

		//var_dump(scandir(self::$path));exit;

		if (count(scandir(self::$path)) - 2 == self::$workCount) {
			exec("cat " . self::$path . "/*.ts >" . self::$path . "/new.mp4");
			exec("rm -f " . self::$path . "/*.ts");
			//exec("mv {$path}/new.tmp {$path}/new.mp4");

			echo "合并完成" . PHP_EOL;
		}

	}

	public static function run($url, $threadCount = 1) {
		self::init($url, $threadCount);

		self::createWork();

		self::endFork();

		self::catFile();
	}

}
//url threadCount
Down::run('', 4);

?>