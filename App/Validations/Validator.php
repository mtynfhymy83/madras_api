<?php

namespace App\Validations;

use App\Database\DB;
use PDO;

class Validator
{
    private array $errors = [];

    public function validate(array $data, array $rules): bool
    {
        $this->errors = [];

        foreach ($rules as $field => $ruleString) {
            $rulesArray = explode('|', $ruleString);

            foreach ($rulesArray as $ruleItem) {
                // جدا کردن پارامترها (مثلاً unique:users,email)
                $params = [];
                if (str_contains($ruleItem, ':')) {
                    [$ruleName, $paramStr] = explode(':', $ruleItem, 2);
                    $params = explode(',', $paramStr);
                } else {
                    $ruleName = $ruleItem;
                }

                $value = $data[$field] ?? null;

                // اگر مقدار خالی بود و رول required نبود، رد شو (مگر اینکه nullable باشد)
                if (empty($value) && $ruleName !== 'required') {
                    continue;
                }

                $this->checkRule($field, $value, $ruleName, $params);
            }
        }

        return empty($this->errors);
    }

    private function checkRule(string $field, $value, string $rule, array $params): void
    {
        switch ($rule) {
            case 'required':
                if (is_null($value) || $value === '') {
                    $this->addError($field, "فیلد $field الزامی است.");
                }
                break;

            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->addError($field, "فرمت ایمیل صحیح نیست.");
                }
                break;

            case 'numeric':
                if (!is_numeric($value)) {
                    $this->addError($field, "$field باید عدد باشد.");
                }
                break;
                
            case 'min':
                $min = (int)$params[0];
                if (is_string($value) && mb_strlen($value) < $min) {
                    $this->addError($field, "حداقل طول باید $min کاراکتر باشد.");
                } elseif (is_numeric($value) && $value < $min) {
                    $this->addError($field, "مقدار نباید کمتر از $min باشد.");
                }
                break;

            case 'in': // in:admin,user
                if (!in_array((string)$value, $params)) {
                    $this->addError($field, "مقدار انتخاب شده معتبر نیست.");
                }
                break;

            // 🔥 پیاده‌سازی امن Unique برای Swoole
            // نحوه استفاده: unique:table_name,column_name,except_id
            case 'unique':
                $table = $params[0] ?? null;
                $column = $params[1] ?? $field;
                $exceptId = $params[2] ?? null;

                if ($table && !$this->isUnique($table, $column, $value, $exceptId)) {
                    $this->addError($field, "این مقدار ($value) قبلاً ثبت شده است.");
                }
                break;
        }
    }

    /**
     * بررسی یکتایی در دیتابیس با استفاده از Connection Pool
     */
    private function isUnique(string $table, string $column, $value, ?string $exceptId = null): bool
    {
        // استفاده از DB::run برای جلوگیری از نشت کانکشن
        return DB::run(function (PDO $pdo) use ($table, $column, $value, $exceptId) {
            // جلوگیری از SQL Injection روی نام جدول و ستون (ساده)
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
            $column = preg_replace('/[^a-zA-Z0-9_]/', '', $column);

            $sql = "SELECT COUNT(*) FROM \"$table\" WHERE \"$column\" = :value";
            $bindings = ['value' => $value];

            // اگر بخواهیم هنگام آپدیت، رکورد فعلی را نادیده بگیریم
            if ($exceptId) {
                $sql .= " AND id != :id";
                $bindings['id'] = $exceptId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($bindings);
            
            return $stmt->fetchColumn() == 0;
        });
    }

    private function addError(string $field, string $msg): void
    {
        $this->errors[$field][] = $msg;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}