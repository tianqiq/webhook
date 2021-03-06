<?php
$config = [
    'basePath' => __DIR__,
    'token' => 'gps',
    'target' => '/path',
    'linuxUser' => 'user',
];
// 1. 实例化应用程序并验证
// 2. 验证 token 是否正确
// 3. 验证程序是否上锁，防止重复操作
// 4. 上锁文件
// ($app = new Application(...array_values($config)))->validateToken()->validateLock()->lock();

try {

    ($app = new Application(...array_values($config)))->validateLock()->lock();
} catch (Exception $e) {

    file_put_contents(
        $this->log,
        sprintf("[%s] %s %s", date('Y-m-d H:i:s'), $e->getMessage(), PHP_EOL),
        FILE_APPEND
    );

    exit(0);
}

// 运行爬取
$app->run();


class Application
{
    /**
     * 日志文件
     *
     * @var string
     */
    protected $log = 'cache/log.txt';

    /**
     * 提交记录文件
     *
     * @var string
     */
    protected $history = 'cache/history.txt';

    /**
     * 项目锁文件，防止冲突
     *
     * @var string
     */
    protected $lockFile = 'cache/.lock.txt';

    /**
     * 项目根目录
     *
     * @var string
     */
    protected $basePath;

    /**
     * token 验证
     *
     * @var string
     */
    protected $token;

    /**
     * 项目文件夹
     *
     * @var string
     */
    protected $target;

    /**
     * linux 用户
     *
     * @var string
     */
    protected $linuxUser;

    public function __construct(string $basePath, string $token, string $target, string $linuxUser)
    {
        $this->basePath = realpath($basePath);
        $this->token = $token;
        $this->target = $target;
        $this->linuxUser = $linuxUser;

        $this->lockFile = $this->basePath.DIRECTORY_SEPARATOR.$this->lockFile;
        $this->log = $this->basePath.DIRECTORY_SEPARATOR.$this->log;
        $this->history = $this->basePath.DIRECTORY_SEPARATOR.$this->history;
    }


    /**
     * 验证 token 合法性
     *
     * @return $this
     * @throws Exception
     */
    public function validateToken()
    {
        $json = json_decode($stream = file_get_contents('php://input'), true);

        if (! isset($json['token']) || $json['token'] != $this->token) {
            throw new Exception('token validate fail, ['.$stream.']');
        }

        return $this;
    }

    /**
     * 验证是否上锁
     *
     * @return $this
     * @throws Exception
     */
    public function validateLock()
    {
        if (is_file($this->lockFile)) {
            throw new Exception('token validate fail');
        }

        return $this;
    }

    /**
     * 上锁，防止重复请求
     */
    public function lock()
    {
        file_put_contents($this->lockFile, ' ');
    }


    /**
     * 运行 git 更新
     */
    public function run()
    {
        $cmd = "cd {$this->target} && sudo -Hu {$this->linuxUser} git pull origin master";
        $result = shell_exec($cmd);

        // 成功记录写到文件
        file_put_contents(
            $this->history,
            sprintf('%s %s %s %s %s %s', str_repeat('#', 40), PHP_EOL, $result, PHP_EOL, str_repeat('#', 40), PHP_EOL),
            FILE_APPEND
        );
    }


    /**
     * 删除上锁文件
     */
    public function __destruct()
    {
        if (is_file($this->lockFile)) {
            unlink($this->lockFile);
        }
    }
}
