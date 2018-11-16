<?php
namespace Swoole\Async;

use Swoole;

class MySQL extends Pool
{
    const DEFAULT_PORT = 3306;

    function __construct($config, $maxConnection = 100)
    {
        if (empty($config['host']))
        {
            $this->log(dirname(dirname(dirname(__DIR__))).'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log',"时间：".date('Ymd-H:i:s',time())."\r\nrequire mysql host option.");
        }
        if (empty($config['port']))
        {
            $config['port'] = self::DEFAULT_PORT;
        }
        parent::__construct($config, $maxConnection);
        $this->create(array($this, 'connect'));
    }
    //记录日志
     private function log($path,$data)
    {
        if (!file_put_contents( $this->exitdir($path), $data."\r\n", FILE_APPEND )) {
            return false;
        }
        return true;
    }
    
    private function exitdir($dir)
    {
        $dirarr=pathinfo($dir);
        if (!is_dir( $dirarr['dirname'] )) {
            mkdir( $dirarr['dirname'], 0777, true);
        }
        return $dir;
    }

    protected function connect()
    {
        $db = new \swoole_mysql;
        $db->on('close', function ($db)
        {
            $this->remove($db);
        });
        return $db->connect($this->config, function ($db, $result)
        {
            if ($result)
            {
                $this->join($db);
            }
            else
            {
                $this->failure();
                $this->log(dirname(dirname(dirname(__DIR__))).'/log/'.date('Ymd',time()).'/'.date('Ymd',time()).'.log',"时间：".date('Ymd-H:i:s',time())."\r\nconnect to mysql server[{$this->config['host']}:{$this->config['port']}] failed. Error: {$db->connect_error}[{$db->connect_errno}].\r\n");
                // trigger_error("connect to mysql server[{$this->config['host']}:{$this->config['port']}] failed. Error: {$db->connect_error}[{$db->connect_errno}].");
            }
        });
    }

    function query($sql, callable $callabck)
    {
        $this->request(function (\swoole_mysql $db) use ($callabck, $sql)
        {
            return $db->query($sql, function (\swoole_mysql $db, $result) use ($callabck)
            {
                call_user_func($callabck, $db, $result);
                $this->release($db);
            });
        });
    }

    function isFree()
    {
        return $this->taskQueue->count() == 0 and $this->idlePool->count() == count($this->resourcePool);
    }

    /**
     * 关闭连接池
     */
    function close()
    {
        foreach ($this->resourcePool as $conn)
        {
            /**
             * @var $conn \swoole_mysql
             */
            $conn->close();
        }
    }

    function __destruct()
    {
        $this->close();
    }
}
