<?php
/**
 * Created by PhpStorm.
 * User: Lejianwen
 * Date: 2017/11/4
 * Time: 15:40
 */

namespace Ljw\Es;

/**
 * Class Query
 * @package App\Library\Es
 */
class Query
{
    protected $must = [];
    protected $should = [];
    protected $must_no = [];
    protected $from = 0;
    protected $size = 10;
    protected $sort = [];
    protected $index = '';
    protected $type = '';
    protected $aggs = [];
    protected $model;
    protected $aggs_alias = [];
    /**
     * @var int 搜索结果总数
     */
    protected $count = 0;
    /**
     * @var array query返回的查询结果
     */
    protected $origin_data;


    /**
     * Query constructor.
     * @param null $index
     * @param null $type
     */
    public function __construct($index = null, $type = null)
    {
        if ($index && $index instanceof Model) {
            $model = $index;
            //优先使用别名
            if ($model->getAlias()) {
                $this->index($model->getAlias());
            } elseif ($model->getIndex()) {
                $this->index($model->getIndex());
            }
            if ($model->getType()) {
                $this->type($model->getType());
            }
            $this->model = $model;
        } elseif ($index !== null && $type !== null) {
            $this->index($index)->type($type);
        }

    }

    /**
     * search
     * @return Model|Query
     * @throws \Exception
     * @author Lejianwen
     */
    public function search()
    {
        try {
            if (!$this->index || !$this->type) {
                throw new \Exception('index and type is need!!!');
            }
            $result = Es::init()->search($this->getParams());
            $data = [];
            if (isset($result['aggregations'])) {
                //聚合查询
                foreach ($this->aggs_alias as $alias) {
                    switch ($alias['type']) {
                        case 'group':
                            $data['source'][$alias['type']] = $result['aggregations'][$alias['alias']]['buckets'];
                            break;
                        case 'max':
                        case 'min':
                        case 'avg':
                        case 'sum':
                            $data['source'][$alias['type']] = $result['aggregations'][$alias['alias']]['value'];
                            break;
                    }
                }
            } else {
                //普通查询
                $data['total'] = $result['hits']['total'];
                $data['source'] = array_column($result['hits']['hits'], '_source');
            }
            if ($this->model) {
                return $this->model->setData($data);
            } else {
                return $this->setData($data);
            }
        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getParams()
    {
        return [
            'index' => $this->getIndex(),
            'type'  => $this->getType(),
            'body'  => $this->getBody()
        ];
    }

    public function index($index)
    {
        $this->index = $index;
        return $this;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function type($type)
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }


    public function getQuery()
    {
        $query = [];
        if ($this->must) {
            $query['bool']['must'] = $this->must;
        } else {
            $query['bool']['must'] = [];
        }
        if ($this->must_no) {
            $query['bool']['must_no'] = $this->must_no;
        }
        if ($this->should) {
            $query['bool']['should'] = $this->should;
        }
        return $query;
    }

    public function getBody()
    {
        $body = [
            'query' => $this->getQuery(),
            'from'  => $this->getFrom(),
            'size'  => $this->getSize(),
            'sort'  => empty($this->getSort()) ? ['_score' => ['order' => 'desc']] : $this->getSort()
        ];
        if ($this->getAggs()) {
            $body['size'] = 0;
            $body['aggs'] = $this->getAggs();
        }
        return $body;
    }

    public function getFrom()
    {
        return $this->from;
    }

    public function getSize()
    {
        return $this->size;
    }

    public function size($size)
    {
        $this->size = $size;
        return $this;
    }

    public function getSort()
    {
        return $this->sort;
    }

    public function where($name, $exp = null, $value = null)
    {
        return $this->_buildWhere('must', $name, $exp, $value);
    }

    public function orWhere($name, $exp = null, $value = null)
    {
        return $this->_buildWhere('should', $name, $exp, $value);
    }

    public function noWhere($name, $exp = null, $value = null)
    {
        return $this->_buildWhere('must_no', $name, $exp, $value);
    }

    public function page($page = 1, $page_size = 10)
    {
        $from = (($page - 1) * $page_size);
        $this->from = $from;
        $this->size = $page_size;
        return $this;
    }

    public function getAggs()
    {
        return $this->aggs;
    }

    public function aggs($aggs)
    {
        $this->aggs = $aggs;
        return $this;
    }

    public function group($field, $_query = null, $alias = '')
    {
        if (!$alias) {
            $alias = 'group_' . $field;
        }
        $this->aggs_alias[] = [
            'field' => $field,
            'alias' => $alias,
            'type'  => 'group'
        ];
        $this->aggs[$alias] = [
            'terms' => [
                'field' => $field,
                'size'  => $this->getSize()
            ]
        ];
        if ($_query && $_query instanceof \Closure) {
            $query = new Query();
            $_query($query);
            $aggs = $query->getAggs();
            $this->aggs[$alias]['aggs'] = $aggs;
        }

        return $this;
    }

    public function avg($field, $alias = '')
    {
        if (!$alias) {
            $alias = 'avg_' . $field;
        }
        $this->aggs_alias[] = [
            'field' => $field,
            'alias' => $alias,
            'type'  => 'avg'
        ];
        $this->aggs[$alias] = [
            'avg' => ['field' => $field]
        ];
        return $this;
    }

    public function max($field, $alias = '')
    {
        if (!$alias) {
            $alias = 'max_' . $field;
        }
        $this->aggs_alias[] = [
            'field' => $field,
            'alias' => $alias,
            'type'  => 'max'
        ];
        $this->aggs[$alias] = [
            'max' => ['field' => $field]
        ];
        return $this;
    }

    public function min($field, $alias = '')
    {
        if (!$alias) {
            $alias = 'min_' . $field;
        }
        $this->aggs_alias[] = [
            'field' => $field,
            'alias' => $alias,
            'type'  => 'min'
        ];
        $this->aggs[$alias] = [
            'min' => ['field' => $field]
        ];
        return $this;
    }

    public function sum($field, $alias = '')
    {
        if (!$alias) {
            $alias = 'sum_' . $field;
        }
        $this->aggs_alias[] = [
            'field' => $field,
            'alias' => $alias,
            'type'  => 'sum'
        ];
        $this->aggs[$alias] = [
            'sum' => ['field' => $field]
        ];
        return $this;
    }

    public function order($name, $type = 'desc')
    {
        if ($type != 'desc' && $type != 'asc') {
            $type = 'desc';
        }
        $this->sort[$name] = ['order' => $type];
        return $this;
    }

    protected function _buildWhere($type, $name, $exp, $value)
    {
        if (!$name) {
            throw new \Exception('name is must');
        }
        $result = [];
        if ($name instanceof \Closure) {
            $query = new Query();
            $name($query);
            $result = $query->getQuery();
        } elseif ($value === null) {
            $value = $exp;
            $result = ['match' => [$name => $value]];
        } else {
            $result = $this->_buildQueryArray($name, $exp, $value);
        }
        if (!empty($result)) {
            array_push($this->$type, $result);
        }
        return $this;
    }

    protected function _buildQueryArray($name, $exp, $value)
    {
        switch ($exp) {
            case '=' :
                $result = ['match' => [$name => $value]];
                break;
            case '>=':
                $result = ['range' => [$name => ['gte' => $value]]];
                break;
            case '>':
                $result = ['range' => [$name => ['gt' => $value]]];
                break;
            case '<=':
                $result = ['range' => [$name => ['lte' => $value]]];
                break;
            case '<':
                $result = ['range' => [$name => ['lt' => $value]]];
                break;
            case 'between':
                $result = ['range' => [$name => ['gte' => $value[0], 'lte' => $value[1]]]];
                break;
            default:
                $result = [];
                break;
        }
        return $result;
    }

    public function setData($result)
    {
        $this->origin_data = $result['source'];
        if (isset($result['total'])) {
            $this->count = $result['total'];
        }
        return $this;
    }

    public function getData()
    {
        return $this->origin_data;
    }

    public function getCount()
    {
        return $this->count;
    }
}