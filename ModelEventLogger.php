<?php

namespace App\Traits;

require config('app.name')."\MF\Activity.php";

use App\Activity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


/**
 * Class ModelEventLogger
 * @package App\Traits
 *
 *  Automatically Log Add, Update, Delete events of Model.
 */
trait ModelEventLogger {

    /**
     * Automatically boot with Model, and register Events handler.
     */
    protected static function bootModelEventLogger()
    {
        foreach (static::getRecordActivityEvents() as $eventName) {
            static::$eventName(function (Model $model) use ($eventName) {
                try {
                    $reflect = new \ReflectionClass($model);
                    return Activity::create([
                        'user_id'     => (!empty(Auth::user()->id) ? Auth::user()->id : ''),
                        'user_name'   => (!empty(Auth::user()->id) ? Auth::user()->name : ''),
                        'content_id'   => $model->id,
                        'model_name' => get_class($model),
                        'action'      => static::getActionName($eventName),
                        'description' => ucfirst($eventName) . " a " . $reflect->getShortName(),
                        'model_data'     => json_encode($model->getDirty()),
                        'ip_address' => ''
                    ]);
                } catch (\Exception $e) {
                    //dd($e);
                    return false;
                }
            });
        }
    }

    /**
     * Set the default events to be recorded if the $recordEvents
     * property does not exist on the model.
     *
     * @return array
     */
    protected static function getRecordActivityEvents()
    {
        if (isset(static::$recordEvents)) {
            return static::$recordEvents;
        }

        return [
            'created',
            'updated',
            'deleted',
        ];
    }

    /**
     * Return Suitable action name for Supplied Event
     *
     * @param $event
     * @return string
     */
    protected static function getActionName($event)
    {
        switch (strtolower($event)) {
            case 'created':
                return 'create';
                break;
            case 'updated':
                return 'update';
                break;
            case 'deleted':
                return 'delete';
                break;
            default:
                return 'unknown';
        }
    }
}
