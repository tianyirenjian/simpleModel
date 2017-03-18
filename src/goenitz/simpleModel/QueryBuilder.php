<?php
/**
 * Created by PhpStorm.
 * User: tianyi
 * Date: 3/15/2017
 * Time: 20:49
 */

namespace Goenitz\SimpleModel;


class QueryBuilder
{
    private $where = null;
    private $nestedWhere = null;
    private $subWhere = null;
    private $binding = [];
    private $fields = ['*'];
    private $skip = null;
    private $limit = null;
    private $orders = null;

    private $connection;
    private $class;

    function __construct($connection, $class)
    {
        $this->connection = $connection;
        $this->class = $class;
    }

    private function getTable()
    {
        return (new $this->class)->getTable();
    }

    public function select($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    public function limit($limit)
    {
        $this->limit = $limit;
        return $this;
    }

    public function skip($skip)
    {
        $this->skip = $skip;
        return $this;
    }

    public function orderBy($column, $desc = 'asc')
    {
        $this->orders[] = [$column, $desc];
        return $this;
    }

    public function where($column, $operator = null, $value = null, $boolean = 'and')
    {
        if ($column instanceof \Closure) {
            $newQuery = $this->newQuery();
            $column($newQuery);
            $this->nestedWhere[][$boolean] = $newQuery->where;
            return $this;
        }
        if (is_array($column)) {
            $this->addArrayOfWheres($column, $boolean);
            return $this;
        }
        if ($operator != 'is' && $value == null) {
            $value = $operator;
            $operator = '=';
        }
        $this->where[][$boolean] = [[$column, $operator, $value]];
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null)
    {
        return $this->where($column, $operator, $value, 'or');
    }

    public function whereIn($column, $values, $not = false, $boolean = 'and')
    {
        $rand = guid();
        $rand = str_replace('-', '', rtrim(ltrim($rand, '{'), '}'));
        $i = 0;
        $values = collect($values)->map(function ($value) use ($rand, &$i) {
            $i ++;
            $key = ':inValue' . $rand . $i;
            $this->binding[$key] = $value;
            return $key;
        })->implode(',');
        $in = $not ? 'not in' : 'in';
        return $this->whereSub($column, $in, "( $values )", $boolean);
    }

    private function whereSub($column, $operator = null, $value = null, $boolean = 'and')
    {
        $this->subWhere[][$boolean] = [$column, $operator, $value];
        return $this;
    }

    private function addArrayOfWheres($condition, $boolean = 'and')
    {
        if (!is_array($condition[0])) {
            if (isset($condition[2])) {
                $this->where[][$boolean] = [$condition];
            } else {
                $this->where[][$boolean] = [[$condition[0], '=', $condition[1]]];
            }
        } else {
            $this->where[][$boolean] = collect($condition)->map(function ($where) {
                if (isset($where[2])) {
                    return $where;
                } else {
                    return [$where[0], '=', $where[1]];
                }
            })->toArray();
        }
        return $this;
    }

    public function get($fields = ['*'])
    {
        if ($fields !== ['*']) {
            $this->fields = $fields;
        }
        $sql = $this->toSql();
        $binding = $this->binding;
        $sth = $this->connection->prepare($sql);
        $sth->execute($binding);
        $data = $sth->fetchAll(\PDO::FETCH_ASSOC);
        return collect($data)->map(function ($row) {
            return $row ? new $this->class($row, true) : null;
        });
    }

    public function first()
    {
        $sql = $this->toSql();
        $sth = $this->connection->prepare($sql);
        $sth->execute($this->binding);
        $data = $sth->fetch(\PDO::FETCH_ASSOC);
        return $data ? new $this->class($data, true) : null;
    }

    public function create($attributes)
    {
        $keys = collect($attributes)->keys();
        $table = $this->getTable();
        $sql = "insert into $table (" . $keys->implode(',') . ") ";
        $holders = $keys->map(function ($value) {
            return ':' . $value;
        });
        $sql .= "values (" . $holders->implode(',') . ")";
        $sth = $this->connection->prepare($sql);
        $data = collect($attributes)->map(function ($value, $key) {
            return [":$key" => $value];
        })->collapse()->toArray();
        $sth->execute($data);
        if ($sth->rowCount()) {
            $lastId = $this->connection->lastInsertId();
            $identifier = (new $this->class)->getIdentifier();
            return $this->where($identifier, $lastId)->first();
        }
        return false;
    }

    public function update($attributes, $condition = [])
    {
        if ($condition !== []) {
            $this->where($condition);
        }
        $rand = guid();
        $rand = str_replace('-', '', rtrim(ltrim($rand, '{'), '}'));
        $i = 0;
        $sets = '';
        foreach ($attributes as $attribute => $value) {
            $holder = ":{$attribute}{$rand}{$i}";
            $sets .= " {$attribute} = $holder,";
            $this->binding[$holder] = $value;
            $i ++;
        }
        $sets = substr($sets, 0, strlen($sets) - 1);
        $table = $this->getTable();
        $where = $this->generateWhere();
        $sql = "update $table set $sets $where";
        $sth = $this->connection->prepare($sql);
        $sth->execute($this->binding);
        return $sth->rowCount();
    }

    public function toSql()
    {
        $where = $this->generateWhere();
        $fields = implode(', ', $this->fields);
        $table = $this->getTable();
        $sql = "select $fields from $table $where";
        if (!is_null($this->orders)) {
            $sql .= ' order by';
            $sql = collect($this->orders)->reduce(function ($sql, $order) {
                $sql .= " {$order[0]} {$order[1]},";
                return $sql;
            }, $sql);
            $sql = substr($sql, 0, strlen($sql) - 1);
        }
        if (!is_null($this->limit)) {
            $sql .= ' limit ' . $this->limit;
        }
        if (!is_null($this->skip)) {
            $sql .= ' offset ' . $this->skip;
        }
        return $sql;
    }

    public function newQuery()
    {
        return new static($this->connection, $this->class);
    }

    public function delete()
    {
        $where = $this->generateWhere();
        $table = $this->getTable();
        $sql = "delete from $table $where";
        $sth = $this->connection->prepare($sql);
        $sth->execute($this->binding);
        return $sth->rowCount();
    }

    private function generateWhere()
    {
        $where  = ' where 1 = 1';
        $i = 0;
        if (!is_null($this->where)) {
            collect($this->where)->each(function ($inWhere, $key) use (&$where, &$i) {
                $where .= ' ' . key($inWhere) . ' (';
                $inWhere = array_first($inWhere);
                $newWhere = collect($inWhere)->reduce(function ($where, $value) use (&$i) {
                    $where .=  ' ' . $value[0] . ' ' . $value[1] . ' :' . $value[0] . $i . ' and';
                    $this->binding[":" . $value[0] . $i] = $value[2];
                    $i ++;
                    return $where;
                }, $where);
                $where = substr($newWhere, 0, strlen($newWhere) - 3) . ')';
            });
        }
        if (!is_null($this->nestedWhere)) {
            collect($this->nestedWhere)->each(function ($inWhere, $key) use (&$where, &$i) {
                $where .= ' ' . key($inWhere) . ' ( 1 = 1 ';
                $inWhere = array_first($inWhere);
                $newWhere = '';
                collect($inWhere)->each(function ($inWhere, $key) use (&$where, &$i, &$newWhere) {
                    $key = key($inWhere);
                    $where .= ' ' . $key . ' (';
                    $inWhere = array_first($inWhere);
                    $newWhere = collect($inWhere)->reduce(function ($where, $value) use (&$i) {
                        $where .=  ' ' . $value[0] . ' ' . $value[1] . ' :' . $value[0] . $i . ' and';
                        $this->binding[":" . $value[0] . $i] = $value[2];
                        $i ++;
                        return $where;
                    }, $where);
                    $where = substr($newWhere, 0, strlen($newWhere) - 3) . ')';
                });
                $newWhere = substr($newWhere, strlen(' ' . $key) - 1, strlen($newWhere));
                $where = substr($newWhere, 0, strlen($newWhere) - 3) . ')';
                $where .= ' ) ';
            });
        }
        if (!is_null($this->subWhere)) {
            collect($this->subWhere)->each(function($inWhere) use (&$where, &$i) {
                $key = key($inWhere);
                $where .= ' ' . $key . ' (';
                $where = collect($inWhere)->reduce(function ($where, $value) use (&$i) {
                    $where .= ' ' . $value[0] . ' ' . $value[1] . $value[2];
                    return $where;
                }, $where);
                $where .= ' ) ';
            });
        }
        return $where;
    }
}