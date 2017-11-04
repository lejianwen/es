<?php
/**
 * Created by PhpStorm.
 * User: Lejianwen
 * Date: 2017/11/4
 * Time: 15:38
 */
namespace Ljw\Es;

use Elasticsearch\ClientBuilder;

/**
 * Class Es
 * @package App\Library\Es
 * @method static Query where($name, $exp = null, $value = null)
 */
class Es
{
    static $hosts;
    static $port;
    public static function init()
    {
        static $client;
        if (!$client) {
            $hosts = [
                self::$hosts . ':' . self::$port,         // IP + Port
            ];
            $clientBuilder = ClientBuilder::create();   // Instantiate a new ClientBuilder
            $clientBuilder->setHosts($hosts);           // Set the hosts
            $client = $clientBuilder->build();
        }
        return $client;
    }

    public static function query($index = null, $type = null)
    {
        return new Query($index, $type);
    }

    /**
     * __callStatic
     * @param $func
     * @param $params
     * @return Query
     * @author Lejianwen
     */
    public static function __callStatic($func, $params)
    {
        $query = static::query();
        return $query->$func(...$params);
    }
}