<?php

namespace App\Helpers;

use PDO;

class TableBuilder {
    
    public static function create($cols, $query, $tableAttr = '', $tableName = '', $perPage = 20, $siteUrl = '/v1') {
        try {
            $pdo = \App\Database\DB::get();
            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
            if ($page < 1) $page = 1;
            $offset = ($page - 1) * $perPage;

            // ==========================================
            // 1. محاسبه هوشمند تعداد کل رکوردها
            // ==========================================
            
            // حذف "ORDER BY" از انتهای کوئری برای سبک شدن شمارش
            // (پیدا کردن آخرین Order By که مربوط به کوئری اصلی است)
            $sqlForCount = $query;
            $orderPos = strrpos(strtoupper($sqlForCount), 'ORDER BY');
            if ($orderPos !== false) {
                // چک میکنیم که order by داخل پرانتز (ساب کوئری) نباشد
                $partAfter = substr($sqlForCount, $orderPos);
                if (strpos($partAfter, ')') === false) {
                    $sqlForCount = substr($sqlForCount, 0, $orderPos);
                }
            }

            // روش استاندارد: رپ کردن کوئری اصلی در یک ساب‌کوئری
            // این روش با تمام کوئری‌های پیچیده و JOIN دار کار می‌کند
            $countSql = "SELECT COUNT(*) FROM ({$sqlForCount}) AS sub_count_table";
            
            $stmtCount = $pdo->prepare($countSql);
            $stmtCount->execute();
            $totalRecords = $stmtCount->fetchColumn();
            
            $totalPages = ceil($totalRecords / $perPage);

            // ==========================================
            // 2. اجرای کوئری اصلی با LIMIT
            // ==========================================
            $query .= " LIMIT $perPage OFFSET $offset";
            
            $stmt = $pdo->prepare($query);
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // ==========================================
            // 3. ساخت HTML جدول (مشابه عکس)
            // ==========================================
            $html = '<div class="table-responsive">';
            $html .= '<table ' . $tableAttr . '>';
            
            // هدر
            $html .= '<thead><tr>';
            foreach ($cols as $header => $col) {
                $thAttr = $col['th-attr'] ?? '';
                $html .= '<th ' . $thAttr . '>' . htmlspecialchars($header) . '</th>';
            }
            $html .= '</tr></thead>';
            
            // بدنه
            $html .= '<tbody>';
            if (empty($rows)) {
                $colsCount = count($cols);
                $html .= "<tr><td colspan='$colsCount' class='text-center' style='padding:30px; background:#fff;'>رکوردی یافت نشد</td></tr>";
            } else {
                foreach ($rows as $row) {
                    $html .= '<tr>';
                    foreach ($cols as $header => $col) {
                        $tdAttr = $col['td-attr'] ?? '';
                        $fieldName = $col['field_name'] ?? '';
                        $value = $row[$fieldName] ?? '';
                        
                        if (isset($col['function']) && is_callable($col['function'])) {
                            $value = $col['function']($value, $row);
                        } elseif (isset($col['html'])) {
                            $value = str_replace(['[FLD]', '[ID]'], [$value, $row['id'] ?? ''], $col['html']);
                        }
                        
                        $html .= '<td ' . $tdAttr . '>' . $value . '</td>';
                    }
                    $html .= '</tr>';
                }
            }
            $html .= '</tbody></table></div>';

            // ==========================================
            // 4. صفحه‌بندی (Pagination Style)
            // ==========================================
            if ($totalPages > 1) {
                $html .= '<div class="row" style="margin-top:10px; background:#fff; padding:10px; border:1px solid #f4f4f4;">';
                
                // سمت راست: اطلاعات نمایش
                $html .= '<div class="col-md-6 text-right" style="line-height:30px; font-size:12px; color:#666;">';
                $start = $offset + 1;
                $end = min($offset + $perPage, $totalRecords);
                $html .= "نمایش $start تا $end از $totalRecords رکورد";
                $html .= '</div>';

                // سمت چپ: دکمه‌ها
                $html .= '<div class="col-md-6 text-left" style="direction:ltr;">';
                $html .= '<ul class="pagination pagination-sm" style="margin:0; float:left;">';
                
                // دکمه‌های قبلی
                if ($page > 1) {
                    $html .= '<li><a href="' . self::getUrl(1) . '">«</a></li>';
                    $html .= '<li><a href="' . self::getUrl($page - 1) . '">‹</a></li>';
                } else {
                    $html .= '<li class="disabled"><span>«</span></li>';
                    $html .= '<li class="disabled"><span>‹</span></li>';
                }

                // شماره صفحات
                // لاجیک نمایش: همیشه صفحه 1، همیشه صفحه آخر، و 2 صفحه قبل و بعد از جاری
                $range = 2;
                for ($i = 1; $i <= $totalPages; $i++) {
                    if ($i == 1 || $i == $totalPages || ($i >= $page - $range && $i <= $page + $range)) {
                        $active = ($i == $page) ? 'class="active"' : '';
                        $style = ($i == $page) ? 'style="background-color:#00c0ef; border-color:#00c0ef;"' : '';
                        $html .= "<li $active><a href='" . self::getUrl($i) . "' $style>$i</a></li>";
                    } elseif ($i == $page - $range - 1 || $i == $page + $range + 1) {
                        $html .= "<li class='disabled'><span>...</span></li>";
                    }
                }

                // دکمه‌های بعدی
                if ($page < $totalPages) {
                    $html .= '<li><a href="' . self::getUrl($page + 1) . '">›</a></li>';
                    $html .= '<li><a href="' . self::getUrl($totalPages) . '">»</a></li>';
                } else {
                    $html .= '<li class="disabled"><span>›</span></li>';
                    $html .= '<li class="disabled"><span>»</span></li>';
                }
                
                $html .= '</ul></div></div>';
            }

            return $html;

        } catch (\Exception $e) {
            // نمایش خطای SQL فقط برای ادمین (در حالت پروداکشن باید لاگ شود)
            return '<div class="alert alert-danger" style="direction:ltr; text-align:left;">' . 
                   '<strong>SQL Error:</strong> ' . htmlspecialchars($e->getMessage()) . 
                   '</div>';
        }
    }

    private static function getUrl($page) {
        $params = $_GET;
        $params['page'] = $page;
        return '?' . http_build_query($params);
    }
}