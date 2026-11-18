<?php

class WaNotification_model {
    private $table = 'wa_notifications';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    /**
     * Catat log pengiriman notifikasi WhatsApp
     */
    public function logNotification($user_id, $phoneNumber, $type, $message, $status = 'sent', $responseInfo = null)
    {
        $this->db->query("INSERT INTO {$this->table} 
                          (user_id, phone_number, type, message, status, response_info, created_at)
                          VALUES 
                          (:user_id, :phone_number, :type, :message, :status, :info, NOW())");
        $this->db->bind('user_id', (int)$user_id);
        $this->db->bind('phone_number', $phoneNumber);
        $this->db->bind('type', $type);
        $this->db->bind('message', $message);
        $this->db->bind('status', $status);
        $this->db->bind('info', $responseInfo);
        $this->db->execute();

        return $this->db->rowCount() > 0;
    }

    /**
     * Ambil riwayat log notifikasi terbaru
     */
    public function getRecentLogs($user_id, $limit = 20)
    {
        $this->db->query("SELECT * FROM {$this->table} 
                          WHERE user_id = :user_id 
                          ORDER BY created_at DESC 
                          LIMIT :limit");
        $this->db->bind('user_id', (int)$user_id);
        $this->db->bind('limit', (int)$limit);
        return $this->db->resultSet();
    }

    /**
     * Hitung ringkasan statistik pengiriman notifikasi
     */
    public function getStats($user_id)
    {
        $this->db->query("SELECT 
                            COUNT(*) as total,
                            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as total_sent,
                            SUM(CASE WHEN status = 'simulated' THEN 1 ELSE 0 END) as total_simulated,
                            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as total_failed
                          FROM {$this->table} 
                          WHERE user_id = :user_id");
        $this->db->bind('user_id', (int)$user_id);
        return $this->db->single() ?: ['total' => 0, 'total_sent' => 0, 'total_simulated' => 0, 'total_failed' => 0];
    }
}
