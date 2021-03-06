<?php

namespace Kalnoy\LaravelCommon;

use Response;
use Session;
use Illuminate\Routing\Controller;
use Illuminate\View\View;
use Illuminate\Support\Contracts\JsonableInterface as Jsonable;
use Illuminate\Support\Contracts\ArrayableInterface as Arrayable;
use Illuminate\Support\Contracts\RenderableInterface as Renderable;
use Illuminate\Http\RedirectResponse;
use Kalnoy\LaravelCommon\Service\Form\Alert;

/**
 * Base controller.
 */
class BaseController extends Controller {

    const OK = 'ok';

    const FAIL = 'fail';

    /**
     * Setup the layout used by the controller.
     *
     * @return void
     */
    protected function setupLayout()
    {
        if ( ! is_null($this->layout))
        {
            $this->layout = \View::make($this->layout);
        }
    }

    /**
     * Set layout meta info.
     *
     * @param string $title
     * @param string $keywords
     * @param string $description
     *
     * @return $this
     */
    public function setMeta($title, $keywords = null, $description = null)
    {
        if ($title instanceof MetaHolderInterface) return $this->setMetaFromModel($title);

        if ( $this->layout instanceof View )
        {
            $this->layout->title = $title;
            $this->layout->keywords = $keywords;
            $this->layout->description = $description;
        }

        return $this;
    }

    /**
     * Set meta from a meta holder.
     *
     * @param MetaHolderInterface $model
     *
     * @return BaseController
     */
    public function setMetaFromModel($model)
    {
        return $this->setMeta($model->getMetaTitle(), $model->getMetaKeywords(), $model->getMetaDescription());
    }

    /**
     * Response json data with status.
     *
     * @param mixed  $data
     * @param string $status
     * @param int    $code
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function responseJson($data, $status = self::OK, $code = 200)
    {
        $type = 'data';

        if ($data instanceof RedirectResponse)
        {
            $data = $data->getTargetUrl();
            $type = 'redirect';
        }
        else if ($data instanceof Renderable)
        {
            $data = $data->render();
            $type = 'html';
        }
        else if ($data instanceof Jsonable)
        {
            $data = $data->toJson();
        }
        else if ($data instanceof Arrayable)
        {
            $data = $data->toArray();
        }
        else if (is_object($data))
        {
            $data = (string)$data;
        }

        return Response::json(compact('status', 'type', 'data'), $code);
    }

}