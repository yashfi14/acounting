<?php

class AIConversation_model {
    private $table = 'ai_conversations';
    private $db;

    public function __construct()
    {
        $this->db = new Database;
    }

    public function getHistory($user_id)
    {
        $query = "SELECT * FROM " . $this->table . " WHERE user_id = :user_id ORDER BY created_at ASC";
        $this->db->query($query);
        $this->db->bind('user_id', $user_id);
        
        return $this->db->resultSet();
    }

    public function saveConversation($user_id, $question, $answer)
    {
        $query = "INSERT INTO " . $this->table . " (user_id, question, answer) VALUES (:user_id, :question, :answer)";
        $this->db->query($query);
        $this->db->bind('user_id', $user_id);
        $this->db->bind('question', $question);
        $this->db->bind('answer', $answer);
        
        $this->db->execute();
        return $this->db->rowCount();
    }

    public function clearHistory($user_id)
    {
        $query = "DELETE FROM " . $this->table . " WHERE user_id = :user_id";
        $this->db->query($query);
        $this->db->bind('user_id', $user_id);
        $this->db->execute();
        return $this->db->rowCount();
    }
}
