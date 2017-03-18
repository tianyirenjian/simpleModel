SimpleModel
=======

仿Laravel Eloquent样式的mysql数据库操作类库。

安装方法
```shell
composer require goenitz/simple-model
```

配置数据库信息

```php
require 'vendor/autoload.php';

\Goenitz\SimpleModel\Model::connect([
    'host' => '127.0.0.1',
    'port' => 3306,
    'database' => 'yourdatabasename',
    'username' => 'yourdatabaseusername',
    'password' => 'yourpassword'
]);
```

示例
----

假设有一个表，有id， title, content三个字段。

首先创建一个类

```php
namespace App;

use Goenitz\SimpleModel\Model;

class Article extends Model
{
    //protected $table = 'articles'; // 表名, 多数情况下会自动使用复数形式
    protected $fillable = ['title', 'content']; // 可以插入的字段
    //protected $identifier = 'id'; // 自增主键，默认为id
    //protected $hidden = ['content']; // 转换json, array 时隐藏的字段

    //用于设置属性,一般情况下不需要
    //protected function setTitleAttribute($value)
    //{
    //    $this->attributes['title'] = strtoupper($value);
    //}

    //获取属性,一般情况下不需要
    //protected function getTitleAttribute()
    //{
    //    return $this->attributes['title'] . 'xyz';
    //}
}
```

现在就可以使用了。

#### 添加数据

```php
use App\Article;

Article::create([
    'title' => 'test',
    'content' => 'just a test'
]);
```
或者

```php
$article = new Article();
$article->title = 'another test';
$article->content = 'another content';
$article->save();
```
也可心一次添加多个

```php
$newArticles = Article::createMany([
    [
        'title' => 'test1',
        'content' => 'content1'
    ],
    [
        'title' => 'test2',
        'content' => 'content2'
    ]
]);
dd($newArticles);
```

#### 查询数据

查询第一条

```php
$article = Article::first();

echo $article->title . '<br>';
echo $article['title'] . '<br>';
echo $article->content . '<br>';
echo $article['content'] . '<br>';
```

查询所有

```php
$articles = Article::all();
```

按主键查询

```php
$article = Article::find(1);
```

使用limit, skip , orderBy 查询

```php
$articles = Article::limit(10)->skip(10)->orderBy('id', 'desc')->get();
dump($articles);
```

使用select 来确定查询的列

```php
$article = Article::select(['id', 'title'])->first();
```

也可以在get方法传入参数来使用

```php
$article = Article::where('id', 50)->get(['title', 'content']);
```

条件查询

```php
$articles = Article::where('id', '50')->get();
$articles = Article::where(['id', '50'])->get();

$articles = Article::where('id', '>', '50')->get();
$articles = Article::where(['id', '>', '50'])->get();

$articles = Article::where([
    ['id', '>', '50'],
    ['title', '<>', 'test'],
])->get();

$articles = Article::where('id', '>', '50')->orWhere('id', '<', '30')->get();

$articles = Article::where('id', '>', '50')->limit(10)->skip(10)->orderBy('id')->get();

//还可以闭包使用，但是不支持闭包内部再使用闭包
$articles = Article::where(function ($query) {
    $query->where(['id', '>', '50']);
    $query->where(['title', '<>', 'test']);
})->get();

$articles = Article::where(function ($query) {
    $query->where([
        ['id', '>', 50],
        ['title', '<>', 'test']
    ]);
})->get()

$articles = Article::where(function ($query) {
    $query->where(['id', '>', 50]);
    $query->orWhere(['title', '<>', 'test']);
})->get()

$articles = Article::whereIn('id', [10, 15, 20])->get();

$articles = Article::whereIn('id', [10, 15, 20], true)->get(); // not in
```

**运行前可以把get改为toSql来查看sql语句，避免错误发生**

**whereIn 目前不建议和其它where连用，会产生不可预知的错误**

#### 修改数据

```php
$article = Article::find(5);
$article->title = 'updated';
$article->save();
```
或者

```php
$article = Article::find(5);
$article->update([
    'title' => 'foo',
    'content' => 'bar'
]);
```

批量修改

```php
Article::where('title', 'test')->update(['title', 'foo']);
Article::whereIn('id', [5, 10, 15])->update(['title', 'foo']);
```

#### 删除数据

删除一条
```php
$article = Article::first();
$article->delete();
```

通过id删除多条

```php
Article::destroy([5, 10, 15]);
//或
Article::destroy(5, 10, 15);
```

通过条件删除

```php
Article::where('id', '18')->delete();
Article::whereIn('id', [19, 20, 21])->delete();
```

#### 格式化数据

Model类实现了Jsonable和Arrayable接口，在Article类中设置 `protected $hidden = ['content']` 可设置要隐藏的列

```php
Article::find(30)->toJson();
//{"id":"30","title":"foo"}

Article::find(30)->toArray();
/*[
    "id" => "30",
    "title" => "foo"
]*/
```

对于通过where等查出来的多条数据也一样适用

写在最后
----------

还有一些操作未实现或者不能通过以上方法完成，可以直接作用PDO来完成

```php
$connection = \Goenitz\SimpleModel\Model::$connection;
// do everything you want.
```
这就是PDO对象了。

因为本类库使用了 illuminate/support 和symfony/var-dumper。所以你可以使用任何它们的方法。
具体可参考 https://laravel.com/docs/5.4/collections .

还有...
--------

#### todo

- toJson, toArray 的类型问题
- where, orWhere, whereIn 方法的优化和修复
- 表之间的关联

#### change logs

0.01 初始版本
