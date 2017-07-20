<?php
/**
 * PHP 5.0 以上
 *
 * @package         Wavephp
 * @author          许萍
 * @copyright       Copyright (c) 2016
 * @link            https://github.com/xpmozong/wavephp2
 * @since           Version 2.0
 *
 */

/**
 * Wavephp Application Mysql Class
 *
 * 数据库类
 *
 * @package         Wavephp
 * @subpackage      db
 * @author          许萍
 *
 */
class Wmysqli extends Db_Abstract
{
    private     $errno;             // 错误信息
    private     $execNums = 0;      // 执行数量

    public function __construct($config) {
        if (isset($config['slave'])) {
            $this->is_single = false;
        }
        $this->config = $config;
    }

    /**
     * 初始化
     *
     * @param array $dbConfig
     *
     */
    protected function _connect($tag)
    {
        $dbport = isset($this->config[$tag]['dbport']) ? $this->config[$tag]['dbport'] : 3306;
        return new mysqli(  $this->config[$tag]['dbhost'],
                            $this->config[$tag]['username'],
                            $this->config[$tag]['password'],
                            $this->config[$tag]['dbname'],
                            $dbport);
    }

    /**
     * 数据库选择
     */
    protected function db_select($tag) {
        return true;
    }

    /**
     * 数据库字符类型选择
     */
    protected function db_set_charset($conn, $charset) {
        return mysqli_set_charset($conn, $charset);
    }

    /**
     * 数据库执行语句
     *
     * @return blooean
     *
     */
    protected function _query($sql, $conn, $is_rw = false, $isTranscationStart)
    {
        $result = false;
        $start_time = microtime(TRUE);
        if ($stmt = $conn->prepare($sql)) {
            $result = $stmt->execute();
            if ($is_rw) {
                $this->execNums = $stmt->affected_rows;
                $stmt->close();
            } else {
                $result = $stmt;
            }

            if (Wave::app()->config['debuger']) {
                Wave::debug_log('database', (microtime(TRUE) - $start_time), $sql);
            }
            if (isset(Wave::app()->config['write_sql_log']) && Wave::app()->config['write_sql_log']) {
                $data = array(  'op'    => 'sql_log',
                                'time'  => time(),
                                'sql'   => $sql,
                                'execute_time'=>(microtime(TRUE) - $start_time));
                $content = json_encode($data);
                $file = Wave::app()->config['write_sql_dir'].'sql_log_'.date('Y-m-d').'.txt';
                Wave::writeCache($file, $content."\n", 'a+');
            }
        } else {
            if ($isTranscationStart) {
                $conn->rollback();
            }

            // 有错误发生
            $this->errno = $conn->error;
            // 强制报错并且die
            $this->msg($conn, $isTranscationStart);
        }

        return $result;
    }

    /**
     * 插入数据
     *
     * @param string $table         表名
     * @param array  $array         数据数组
     *
     * @return boolean
     *
     */
    protected function _insertdb($table, $array, $isTranscationStart)
    {
        $tbcolumn = $tbvalue = '';
        foreach ($array  as $key=>$value) {
            $value = $this->escape($value);
            $tbcolumn .= '`'.$key.'`'.",";
            $tbvalue  .= ''.$value.',';
        }
        $tbcolumn = "(".trim($tbcolumn,',').")";
        $tbvalue = "(".trim($tbvalue,',').")";
        $sql = "INSERT INTO `".$table."` ".$tbcolumn." VALUES ".$tbvalue;
        return $this->dbquery($sql, $isTranscationStart);
    }

    /**
     * 获得刚插入数据的id
     *
     * @return int id
     *
     */
    protected function _insertId($conn)
    {
        return $conn->insert_id;
    }

    /**
     * 更新数据
     *
     * @param string $table         表名
     * @param array  $array         数据数组
     * @param string $conditions    条件
     *
     * @return boolean
     *
     */
    protected function _updatedb($table, $array, $conditions, $isTranscationStart)
    {
        $update = array();
        foreach ($array as $key => $value) {
            $value = $this->escape($value);
            $update[] = "`$key`=$value";
        }
        $update = implode(",", $update);
        $sql = 'UPDATE `'.$table.'` SET '.$update.' WHERE '.$conditions;
        return $this->dbquery($sql, $isTranscationStart);
    }

    /**
     * 获得刚执行完的条数
     *
     * @return int num
     *
     */
    protected function _affectedRows($conn)
    {
        return $this->execNums;
    }

    /**
     * 自定义处理$stmt->fetch数组
     */
    private function fetchAssocStatement($stmt)
    {
        if ($stmt->num_rows > 0) {
            $row = array();
            $md = $stmt->result_metadata();
            $params = array();
            while ($field = $md->fetch_field()) {
                $params[] = &$row[$field->name];
            }
            call_user_func_array(array($stmt, 'bind_result'), $params);
            if ($stmt->fetch()) {
                return $row;
            }
        }

        return null;
    }

    /**
     * 获得查询语句单条结果
     *
     * @return array
     *
     */
    protected function _getOne($sql, $isTranscationStart)
    {
        $arr = array();
        $stmt = $this->dbquery($sql, $isTranscationStart);
        if ($stmt) {
            $stmt->store_result();
            while ($assoc_array = $this->fetchAssocStatement($stmt)) {
                $arr = $assoc_array;
            }
        }
        $stmt->close();

        return $arr;
    }

    /**
     * 获得查询语句多条结果
     *
     * @return array
     *
     */
    protected function _getAll($sql, $isTranscationStart)
    {
        $arr = array();
        $stmt = $this->dbquery($sql, $isTranscationStart);
        if ($stmt) {
            $stmt->store_result();
            while ($assoc_array = $this->fetchAssocStatement($stmt)) {
                $arr[] = $assoc_array;
            }
        }
        $stmt->close();

        return $arr;
    }

    /**
     * 删除数据
     *
     * @param string $table         表名
     * @param string $fields        条件
     *
     * @return boolean
     *
     */
    protected function _delete($table, $fields, $isTranscationStart)
    {
        $sql = "DELETE FROM $table WHERE $fields";

        return $this->dbquery($sql, $isTranscationStart);
    }

    /**
     * 事务是否自动提交
     */
    protected function _setAutocmit($conn, $autocmit)
    {
        return $conn->autocommit($autocmit);
    }

    /**
     * 事务提交
     */
    public function _transcationCommit($conn)
    {
        $rs = $conn->commit();
        $conn->autocommit(true);
        
        return $rs;
    }

    /**
     * table 加 `
     */
    protected function _table($table) {
        return '`' .$table. '`';
    }

    /**
     * table list
     */
    protected function _list_tables($dbname) {
        $sql = 'SHOW TABLES FROM `'.$dbname.'`';

        return $this->_getAll($sql);
    }

    /**
     * table columns
     */
    protected function _list_columns($table) {
        $sql = 'SHOW COLUMNS FROM '.$this->_table($table);

        return $this->getAll($sql);
    }

    /**
     * 清空表
     */
    protected function _truncate($table) {
        $sql = 'TRUNCATE '.$this->_table($table);

        return $this->dbquery($sql);
    }

    /**
     * mysql limit
     */
    protected function _limit($offset, $limit) {
        if ($offset == 0) {
            $offset = '';
        } else {
            $offset .= ", ";
        }

        return " LIMIT ".$offset.$limit;
    }

    /**
     * 关闭数据库连接，当您使用持续连接时该功能失效
     *
     * @return blooean
     *
     */
    protected function _close($conn)
    {
        return @$conn->close();
    }

    /**
     * 显示自定义错误
     */
    protected function msg()
    {
        if ($this->errno && !empty(Wave::app()->config['crash_show_sql'])) {
            echo $this->getLastSql()."<br>";
            echo "<div style='color:red;'>\n";
                echo "<h4>数据库操作错误</h4>\n";
                echo "<h5>错误信息：".$this->errno."</h5>\n";
            echo "</div>";
            die;
        } else {
            exit('数据库操作错误');
        }
    }
}

?>
