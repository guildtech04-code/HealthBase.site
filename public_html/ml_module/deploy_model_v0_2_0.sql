-- Deploy LightGBM v0.2.0 as current model

INSERT INTO ml_models (
  model_version, created_at, notes, metrics_json, threshold, feature_version, 
  trained_on_start, trained_on_end, test_metrics_json, deployed, deployment_date
) VALUES (
  'v0.2.0', NOW(), 'LightGBM accuracy-optimized model', NULL, 0.59, 'v2',
  '2023-01-01', '2024-12-31', NULL, 1, NOW()
)
ON DUPLICATE KEY UPDATE
  threshold = VALUES(threshold),
  feature_version = VALUES(feature_version),
  deployed = 1,
  deployment_date = NOW();

-- Optionally mark other models as not deployed
UPDATE ml_models SET deployed = 0 WHERE model_version <> 'v0.2.0';

