-- ============================================================================
-- RISK PREDICTION AND PATIENT PROGRESS MONITORING MODULE
-- Database Schema for ML Models, Predictions, and Follow-up Queue
-- ============================================================================

-- Table: ml_models
-- Stores model artifacts, versions, and metrics
CREATE TABLE IF NOT EXISTS ml_models (
    model_id INT AUTO_INCREMENT PRIMARY KEY,
    model_version VARCHAR(50) UNIQUE NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    metrics_json JSON,
    threshold DECIMAL(5,4) NOT NULL,
    feature_version VARCHAR(20),
    trained_on_start DATE,
    trained_on_end DATE,
    test_metrics_json JSON,
    deployed BOOLEAN DEFAULT FALSE,
    deployment_date DATETIME NULL,
    INDEX idx_deployed (deployed)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: ml_predictions
-- Stores predictions for each visit
CREATE TABLE IF NOT EXISTS ml_predictions (
    prediction_id INT AUTO_INCREMENT PRIMARY KEY,
    visit_id VARCHAR(100) NOT NULL,
    patient_id VARCHAR(100) NOT NULL,
    score DECIMAL(8,6) NOT NULL,
    risk_tier VARCHAR(20) NOT NULL,
    model_version VARCHAR(50) NOT NULL,
    threshold DECIMAL(5,4) NOT NULL,
    scored_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    features_json JSON,
    INDEX idx_visit_id (visit_id),
    INDEX idx_patient_id (patient_id),
    INDEX idx_scored_at (scored_at),
    INDEX idx_risk_tier (risk_tier)
    -- Foreign key removed to avoid constraint issues
    -- FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: followup_queue
-- Stores automated follow-up recommendations
CREATE TABLE IF NOT EXISTS followup_queue (
    followup_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(100) NOT NULL,
    visit_id VARCHAR(100) NOT NULL,
    priority_level VARCHAR(20) NOT NULL,
    suggested_date DATE NOT NULL,
    reason TEXT,
    model_version VARCHAR(50),
    status VARCHAR(20) DEFAULT 'Pending',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    resolved_by VARCHAR(100) NULL,
    notes TEXT,
    INDEX idx_patient_id (patient_id),
    INDEX idx_status (status),
    INDEX idx_suggested_date (suggested_date),
    INDEX idx_priority_level (priority_level)
    -- Foreign key removed to avoid constraint issues
    -- FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: audit_ml
-- Tracks all ML-related actions for audit
CREATE TABLE IF NOT EXISTS audit_ml (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    action VARCHAR(50) NOT NULL,
    actor VARCHAR(100) NOT NULL,
    payload_json JSON,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Table: patient_health_trends
-- Stores aggregated health trends for dashboard
CREATE TABLE IF NOT EXISTS patient_health_trends (
    trend_id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id VARCHAR(100) NOT NULL,
    recent_status VARCHAR(20) NOT NULL,
    trend_label VARCHAR(20) NOT NULL,
    last_consultation_date DATE,
    recent_visits_count INT,
    avg_risk_score DECIMAL(8,6),
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_patient_id (patient_id),
    INDEX idx_trend_label (trend_label)
    -- Foreign key removed to avoid constraint issues
    -- FOREIGN KEY (patient_id) REFERENCES patients(patient_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert initial model record (placeholder for v0.1.0)
INSERT INTO ml_models (
    model_version, notes, threshold, feature_version, trained_on_start, trained_on_end
) VALUES (
    'v0.1.0',
    'Initial ML Risk Prediction Model - Training pending',
    0.47,
    'v1',
    '2023-01-01',
    '2024-12-31'
) ON DUPLICATE KEY UPDATE model_version = model_version;

