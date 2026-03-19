<?php

namespace App\Observers;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GeneralObserver
{
    public function created(Model $model)
    {
        $this->log($model, 'Inserted');
    }

    public function updated(Model $model)
    {
        // Ignore updated event caused by soft delete
        if ($this->isSoftDeleting($model)) {
            return;
        }

        $this->log($model, 'Updated');
    }

    public function deleted(Model $model)
    {
        $this->log($model, 'Deleted');
    }

    public function restored(Model $model)
    {
        $this->log($model, 'Restored');
    }

    protected function isSoftDeleting(Model $model): bool
    {
        return in_array(
            SoftDeletes::class,
            class_uses_recursive($model)
        ) && $model->isDirty('deleted_at');
    }

    protected function log(Model $model, string $action)
    {
        ActivityLog::create([
            'user_id'    => auth()->id(),
            'action'     => $action,
            'model_name' => get_class($model),
            'model_id'   => $model->getKey(),
            'before'     => $action === 'Inserted'
                ? null
                : array_intersect_key($model->getOriginal(), $model->getDirty()),
            'after'      => $action === 'Inserted'
                ? $model->getAttributes()
                : $model->getDirty(),
            'ip_address' => request()->ip(),
        ]);
    }
}
