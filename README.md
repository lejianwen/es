# es使用

* 项目要用到es，所以自己进行了一下简单的封装

* es版本2.3

## 安装

composer require ljw/es:dev-master

## 配置

~~~php
//配置es
\Ljw\Es\Es::$hosts = '127.0.0.1';
\Ljw\Es\Es::$port = '9200';
~~~

## 开始使用

### 建立索引

* 创建一个类继承model

~~~php
class Product extends \Ljw\Es\Model
{
    //指定索引
    protected $index = 'product_index';
    //指定索引别名
    protected $alias = 'product_alias';
    //指定type
    protected $type = 'products';
    /**
     * 索引设置
     * @var array
     */
    protected $setting = [];
    /**
     * 索引参数
     * @var array
     */
    protected $properties = [
        'id'             => ['type' => 'long'],
        'name'           => ['type' => 'string'],
        'description'    => ['type' => 'string'],
    ];
    
}
~~~

* 注：如果没有在类里面指定index，alias和type,会自动设置为

 {class}_index | {class}_alias  |  {class}_type

**{class}** 为类名小写

* 运行
~~~php
//创建索引。会根据Product中的$setting，$properties来创建
Product::createIndex();
//删除索引
Product::deleteIndex();
//跟新索引。根据Product中的$properties来跟新，只能进行追加，不能修改和删除字段
Product::updateIndex();
~~~
---

### 查询

1. 直接查询
~~~php
//简单查询,where只支持 = , > , < , >= , <= , between ; = 就相当于like了
// where ... or ... and (... or ...) order by `id` `desc` 
$query = \Ljw\Es\Es::query()
    ->index('pro_index')
    ->type('type')
    ->where(...)
    ->orWhere(...)
    ->where(function ($q) {
        $q->where(...)->orWhere(...);
    })
    ->order('id', 'desc')
    ->page($page, $page_size)
    ->search();
$list = $query->getData(); //数据列表
$count = $query->getCount(); //数据总量
// ---------------
// 聚合，支持 group,avg,sum,max,min
$query = \Ljw\Es\Es::query()
    ->index('pro_index')
    ->type('type')
    ->where(...)
    ->group('xxx', function($q){  //  group by xxx 并计算不同xxx下面aaa的和
        $q->sum('aaa');
    })
    ->sum('id') // 计算id的和
    ->search();
$data = $query->getData();
~~~
2. 创建模型查询
~~~php
class Product extends \Ljw\Es\Model
{
    //指定索引
    protected $index = 'product_index';
    //指定索引别名
    protected $alias = 'product_alias';
    //指定type
    protected $type = 'products';
    protected $setting = [...];
    protected $properties = [...];
    
}
$re = Product::query()->where(...)->orWhere(...)->search();
$list = $re->getData(); //数据列表
$count = $re->getCount(); //数据总量
$re = Product::query()->where(...)->orWhere(...)->group(...)->search();
$data = $re->getData();
~~~

### 添加，修改，删除数据

* 建立模型
~~~php
class Product extends \Ljw\Es\Model
{
    //指定索引
    protected $index = 'product_index';
    //指定索引别名
    protected $alias = 'product_alias';
    //指定type
    protected $type = 'products';
    protected $setting = [...];
    protected $properties = [...];
    
}
// $id 必须为唯一id， $data为数据，会根据 $properties 自动剔除
Product::add($id, $data);
//删除
Product::delete($id);
//跟新数据
Product::update($id, $data);
~~~

# 注：暂时就这些简单的功能了

