<?php

namespace App\Services\Admin;

use App\Database\DB;
use App\Helpers\JalaliHelper;

class PaymentService
{
    public function getTransactionsList(array $params = []): array
    {
        $page = (int)($params['page'] ?? 1);
        $limit = (int)($params['limit'] ?? 20);
        $page = max(1, $page);
        $limit = min(100, max(1, $limit));

        $where = [];
        $bindings = [];

        if (!empty($params['id'])) {
            $where[] = 't.id = ?';
            $bindings[] = (int)$params['id'];
        }

        if (!empty($params['ref_id'])) {
            $where[] = 't.ref_id = ?';
            $bindings[] = trim((string)$params['ref_id']);
        }

        if (isset($params['status']) && $params['status'] !== '') {
            $where[] = 't.status = ?';
            $bindings[] = (int)$params['status'];
        }

        if (!empty($params['price'])) {
            $where[] = 't.price = ?';
            $bindings[] = (int)$params['price'];
        }

        if (!empty($params['section'])) {
            $where[] = 't.section = ?';
            $bindings[] = trim((string)$params['section']);
        }

        if (!empty($params['username'])) {
            $where[] = 'u.username ILIKE ?';
            $bindings[] = '%' . trim((string)$params['username']) . '%';
        }

        if (!empty($params['email'])) {
            $where[] = 'u.email ILIKE ?';
            $bindings[] = '%' . trim((string)$params['email']) . '%';
        }

        if (!empty($params['mobile'])) {
            $where[] = 'u.mobile ILIKE ?';
            $bindings[] = '%' . trim((string)$params['mobile']) . '%';
        }

        if (!empty($params['from_date'])) {
            $fromTs = strtotime($params['from_date'] . ' 00:00:00');
            if ($fromTs !== false) {
                $where[] = 't.cdate >= ?';
                $bindings[] = $fromTs;
            }
        }

        if (!empty($params['to_date'])) {
            $toTs = strtotime($params['to_date'] . ' 23:59:59');
            if ($toTs !== false) {
                $where[] = 't.cdate <= ?';
                $bindings[] = $toTs;
            }
        }

        $whereSql = '';
        if (!empty($where)) {
            $whereSql = 'WHERE ' . implode(' AND ', $where);
        }

        $countSql = "
            SELECT COUNT(*) AS total
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            $whereSql
        ";
        $countRow = DB::fetch($countSql, $bindings);
        $total = (int)($countRow['total'] ?? 0);

        $offset = ($page - 1) * $limit;
        $sql = "
            SELECT
                t.id,
                t.user_id,
                t.status,
                t.state,
                t.cprice,
                t.price,
                t.discount,
                t.discount_id,
                t.paid,
                t.ref_id,
                t.cdate,
                t.pdate,
                t.owner,
                t.section,
                t.data_id,
                u.username,
                u.mobile,
                u.email
            FROM transactions t
            LEFT JOIN users u ON u.id = t.user_id
            $whereSql
            ORDER BY t.id DESC
            LIMIT ? OFFSET ?
        ";
        $rows = DB::fetchAll($sql, array_merge($bindings, [$limit, $offset]));

        $items = array_map(function ($row) {
            $status = isset($row['status']) ? (int)$row['status'] : null;
            $statusLabel = $status === 0 ? 'پرداخت موفق' : 'در انتظار پرداخت';

            return [
                'id' => (int)$row['id'],
                'user_id' => isset($row['user_id']) ? (int)$row['user_id'] : null,
                'user' => [
                    'username' => $row['username'] ?? null,
                    'email' => $row['email'] ?? null,
                    'mobile' => $row['mobile'] ?? null,
                ],
                'status' => $status,
                'status_label' => $statusLabel,
                'state' => $row['state'] ?? null,
                'cprice' => (int)($row['cprice'] ?? 0),
                'price' => (int)($row['price'] ?? 0),
                'discount' => (int)($row['discount'] ?? 0),
                'discount_id' => isset($row['discount_id']) ? (int)$row['discount_id'] : null,
                'paid' => (int)($row['paid'] ?? 0),
                'ref_id' => $row['ref_id'] ?? null,
                'cdate' => isset($row['cdate']) ? (int)$row['cdate'] : null,
                'pdate' => isset($row['pdate']) ? (int)$row['pdate'] : null,
                'cdate_jalali' => isset($row['cdate']) ? JalaliHelper::toJalali((int)$row['cdate'], 'Y/m/d H:i') : null,
                'pdate_jalali' => isset($row['pdate']) ? JalaliHelper::toJalali((int)$row['pdate'], 'Y/m/d H:i') : null,
                'section' => $row['section'] ?? null,
                'data_id' => $row['data_id'] ?? null,
            ];
        }, $rows);

        return [
            'items' => $items,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'pages' => (int)ceil($total / $limit),
            ],
        ];
    }
}
