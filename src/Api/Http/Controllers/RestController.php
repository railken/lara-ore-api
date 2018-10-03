<?php

namespace Railken\Amethyst\Api\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use League\Fractal;
use League\Fractal\Pagination\IlluminatePaginatorAdapter;
use League\Fractal\Serializer\JsonApiSerializer;
use League\Fractal\TransformerAbstract;
use Railken\Amethyst\Api\Transformers\BaseTransformer;
use Railken\Bag;
use Railken\Lem\Contracts\EntityContract;

abstract class RestController extends Controller
{
    /**
     * @var string
     */
    public $name;

    /**
     * @var string
     */
    public $transformerClass = BaseTransformer::class;

    /**
     * @var Bag
     */
    public $keys;

    /**
     * @var \Railken\Lem\Contracts\ManagerContract
     */
    public $manager;

    /**
     * @var array
     */
    public $queryable = [];

    /**
     * @var array
     */
    public $selectable = [];

    /**
     * @var array
     */
    public $sortable = [];

    /**
     * @var array
     */
    public $fillable = [];

    /**
     * Construct.
     */
    public function __construct()
    {
        $this->keys = new Bag();
        $this->keys->set('queryable', $this->queryable);
        $this->keys->set('selectable', collect(empty($this->selectable) ? $this->queryable : $this->selectable));
        $this->keys->set('sortable', collect(empty($this->sortable) ? $this->queryable : $this->sortable));
        $this->keys->set('fillable', $this->fillable);
    }

    /**
     * Retrieve resource name.
     *
     * @return string
     */
    public function getResourceName()
    {
        return $this->name !== null ? $this->name : strtolower(str_replace('Controller', '', (new \ReflectionClass($this))->getShortName()));
    }

    /**
     * Return a new instance of Manager.
     *
     * @return \Railken\Lem\Contracts\ManagerContract
     */
    public function getManager()
    {
        return $this->manager;
    }

    /**
     * Parse the key before using it in the query.
     *
     * @param string $key
     *
     * @return string
     */
    public function parseKey($key)
    {
        $keys = explode('.', $key);

        if (count($keys) === 1) {
            $keys = [$this->getManager()->getRepository()->newEntity()->getTable(), $keys[0]];
        }

        return DB::raw('`'.implode('.', array_slice($keys, 0, -1)).'`.'.$keys[count($keys) - 1]);
    }

    /**
     * Create a new instance for query.
     *
     * @return \Illuminate\Database\Query\Builder
     */
    public function getQuery()
    {
        return $this->getManager()->getRepository()->getQuery();
    }

    /**
     * Create a new instance of fractal transformer.
     *
     * @param \Railken\Lem\Contracts\EntityContract $entity
     * @param \Illuminate\Http\Request              $request
     *
     * @return TransformerAbstract
     */
    public function getFractalTransformer(EntityContract $entity, Request $request): TransformerAbstract
    {
        $classTransformer = $this->transformerClass;

        return new $classTransformer($this->getManager(), $entity, $request);
    }

    /**
     * Retrieve url base.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return string
     */
    public function getResourceBaseUrl(Request $request): string
    {
        return $request->getSchemeAndHttpHost().Config::get('ore.api.http.'.explode('.', Route::getCurrentRoute()->getName())[0].'.router.prefix');
    }

    /**
     * Retrieve fractal manager.
     *
     * @param \Illuminate\Http\Request $request
     *
     * @return Fractal\Manager;
     */
    public function getFractalManager(Request $request)
    {
        $manager = new Fractal\Manager();
        $manager->setSerializer(new JsonApiSerializer($this->getResourceBaseUrl($request)));

        if ($request->input('include') !== null) {
            $manager->parseIncludes($request->input('include'));
        }

        return $manager;
    }

    /**
     * Serialize entity.
     *
     * @param \Railken\Lem\Contracts\EntityContract $entity
     * @param \Illuminate\Http\Request              $request
     *
     * @return array
     */
    public function serialize(EntityContract $entity, Request $request)
    {
        $transformer = $this->getFractalTransformer($entity, $request);

        $resource = new Fractal\Resource\Item($entity, $transformer, $this->getResourceName());

        return $this->getFractalManager($request)->createData($resource)->toArray();
    }

    /**
     * Serialize a collection.
     *
     * @param Collection               $collection
     * @param \Illuminate\Http\Request $request
     * @param mixed                    $paginator
     *
     * @return array
     */
    public function serializeCollection(Collection $collection, Request $request, $paginator)
    {
        $transformer = $this->getFractalTransformer($collection->get(0, null), $request);

        $resource = new Fractal\Resource\Collection($collection, $transformer, $this->getResourceName());
        $resource->setPaginator(new IlluminatePaginatorAdapter($paginator));

        return $this->getFractalManager($request)->createData($resource)->toArray();
    }
}
