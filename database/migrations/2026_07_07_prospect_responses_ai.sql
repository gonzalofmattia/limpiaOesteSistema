ALTER TABLE prospect_responses
    ADD COLUMN ai_intent VARCHAR(30) DEFAULT NULL AFTER processed,
    ADD COLUMN ai_suggested_reply TEXT DEFAULT NULL AFTER ai_intent,
    ADD COLUMN ai_processed_at DATETIME DEFAULT NULL AFTER ai_suggested_reply;
