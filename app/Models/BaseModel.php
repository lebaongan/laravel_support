<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use DB;

class BaseModel extends Model
{
    public static function getTableName()
    {
        return (new static)->getTable();
    }

    /**
     * Call relation if relation satisfy the conditions
     *
     * @param QueryBuilder $query
     * @param string $relation: relation in model
     * @param callback $constraint: condition
     * @return QueryBuilder
     */
    public function scopeWithWhereHas($query, $relation, $constraint)
    {
        return $query->whereHas($relation, $constraint)->with([$relation => $constraint]);
    }

    /**
     * Filter whereIn sort by pass array variable
     *
     * @param QueryBuilder $query
     * @param string $column: column filter
     * @param array $arrayData: list variable
     * @return QueryBuilder
     */
    public function scopeWhereInOrder($query, $column, $arrayData)
    {
        $arrayData = array_filter($arrayData, function ($data) {
            return $data;
        });

        if (!count($arrayData)) {
            return $query->whereIn($column, $arrayData);
        }

        $dataOrder = implode(',', $arrayData);

        return $query->whereIn($column, $arrayData)
            ->orderByRaw(DB::raw("FIELD({$column},{$dataOrder})"));
    }

    /**
     * Update multiple record by one query
     *
     * @param array $updateData
     * @param array $params: optional
     * @return mixed
     */
    public function updateMultiple($updateData, $params = [])
    {
        try {
            $cases = [];
            $ids = [];

            if (!count($params)) {
                $params = array_keys($updateData[0]);
            }

            foreach ($params as $param) {
                ${"{$param}Params"} = [];
            }
    
            foreach ($updateData as $data) {
                $id = (int) $data['id'];
                $cases[] = "WHEN {$id} THEN ?";
                $ids[] = $id;
    
                foreach ($params as $param) {
                    ${"{$param}Params"}[] = $data["$param"];
                }
            }
    
            $ids = implode(',', $ids);
            $cases = implode(' ', $cases);
            $updateParams = [];
            $paramUpdateSql = '';
            foreach ($params as $param) {
                $updateParams = array_merge($updateParams, ${"{$param}Params"});
                $paramUpdateSql = $paramUpdateSql . "`$param` = CASE `id` {$cases} END,";
            }
            $updateParams[] = now();
    
            $updateSql = "UPDATE `{$this->getTableName()}`"
                . ' SET '
                . $paramUpdateSql
                . " `updated_at` = ? WHERE `id` IN ({$ids})";
    
            return DB::update($updateSql, $updateParams);
        } catch (Exception $e) {
            Log::error($e);
    
            return false;
        }
    }
}
