# ML Risk Prediction Module

**Objective 3:** Risk Prediction and Patient Progress Monitoring

This module provides machine learning-based risk assessment for patient early return visits and health deterioration.

## Overview

The ML Risk Prediction Module uses machine learning models to:
- Identify patients at risk of early return visits (<30 days)
- Classify health trends as Improving, Stable, or At-Risk
- Automatically generate follow-up recommendations for high-risk patients
- Provide actionable insights for preventive care decisions

## Architecture

```
ml_module/
├── ml_model_training.ipynb    # Colab notebook for model training
├── batch_scorer.py             # Python script for batch scoring
├── config.yaml                  # Configuration file
├── requirements.txt            # Python dependencies
├── database_schema.sql         # Database tables for ML data
└── README.md                   # This file
```

## Setup Instructions

### 1. Database Setup

Run the SQL schema to create the required tables:

```bash
mysql -u root -p healthbase < ml_module/database_schema.sql
```

This creates:
- `ml_models` - Stores model metadata and versions
- `ml_predictions` - Stores predictions for each visit
- `followup_queue` - Automated follow-up recommendations
- `patient_health_trends` - Aggregated patient health trends
- `audit_ml` - ML operation audit log

### 2. Model Training (Colab)

1. Upload the data files to Google Colab:
   - `mmc_opd_visits_2023_2025.csv`
   - `mmc_monthly_kpis_2023_2025.csv`

2. Open `ml_model_training.ipynb` in Colab

3. Run all cells to:
   - Load and explore data
   - Engineer features
   - Train models (Logistic Regression + XGBoost)
   - Select optimal threshold (target: 70% recall)
   - Generate SHAP explanations
   - Export artifacts: `model.pkl`, `feature_list.json`, `threshold.json`

4. Download the artifacts to your server:
   ```bash
   scp model.pkl feature_list.json threshold.json user@host:/path/to/ml_module/
   ```

### 3. Batch Scoring Setup

Install Python dependencies:

```bash
pip install -r ml_module/requirements.txt
```

Configure the database connection in `config.yaml`:

```yaml
db:
  host: 'localhost'
  user: 'root'
  password: ''
  database: 'healthbase'
```

Run batch scoring:

```bash
# Score all visits from CSV (for testing)
python ml_module/batch_scorer.py --all

# Score visits since a specific date
python ml_module/batch_scorer.py --since 2025-01-01

# Score recent visits (last 24 hours)
python ml_module/batch_scorer.py
```

### 4. Automation (Cron Job)

Set up daily batch scoring:

```bash
# Edit crontab
crontab -e

# Add this line to run at 1 AM daily
0 1 * * * cd /path/to/project && /usr/bin/python3 ml_module/batch_scorer.py
```

## PHP Integration

### Dashboard

Access the ML dashboard at:
```
http://yourdomain/assistant_view/ml_dashboard.php
```

Features:
- View high-risk patients
- Monitor prediction trends
- Manage follow-up queue
- Review model performance

### API Endpoint

Real-time scoring API:
```
POST http://yourdomain/assistant_view/api/ml_score.php
Content-Type: application/json

{
  "visit_id": "abc-123"
}
```

Response:
```json
{
  "score": 0.65,
  "risk_tier": "High",
  "threshold": 0.47,
  "model_version": "v0.1.0"
}
```

## Features

### 1. Automated Risk Scoring

When a visit is recorded, the system:
1. Extracts features (vitals, diagnosis, prior visits, etc.)
2. Scores the patient using the ML model
3. Determines risk tier (Low/High)
4. Stores prediction in database

### 2. Follow-up Queue

For high-risk patients (score ≥ threshold):
- Automatically added to follow-up queue
- Suggested date: 7-14 days out
- Priority level assigned based on score
- PWD patients always get priority

### 3. Patient Progress Monitoring

Health trends are categorized as:
- **Improving** - Risk scores decreasing over time
- **Stable** - Consistent risk level
- **At-Risk** - Risk scores increasing

### 4. Alert System

Automated alerts trigger when:
- High-risk percentage exceeds 20%
- Feature drift detected (>20% change)
- Patient scores above certain thresholds

## Configuration

Edit `config.yaml` to customize:

```yaml
# Risk threshold (0-1)
threshold: 0.47

# Follow-up settings
followup:
  days_ahead: 10
  priority_threshold: 0.47
  pwd_always_priority: true

# Alert thresholds
alerts:
  high_risk_percentage_threshold: 20
  drift_threshold: 0.20
```

## Model Retraining

Retrain the model quarterly or when:
- New data available (6+ months)
- Performance degrades
- New features added

Steps:
1. Run updated Colab notebook
2. Compare with current model
3. Deploy if performance is better
4. Update `ml_models` table

## Monitoring

### Metrics to Track

- **Accuracy**: How well the model predicts returns
- **Recall**: % of actual returns caught (target ≥70%)
- **Precision**: % of flagged patients who actually return
- **Coverage**: % of visits flagged as high-risk (target 10-20%)

### Model Performance

View current metrics:
```sql
SELECT metrics_json, threshold, created_at 
FROM ml_models 
WHERE deployed = 1
ORDER BY created_at DESC 
LIMIT 1;
```

## Troubleshooting

### "No deployed model found"
- Ensure you've run `database_schema.sql` and inserted model record
- Check that a model is marked as `deployed = 1`

### Predictions not generating
- Verify model artifacts exist (model.pkl, feature_list.json)
- Check database connectivity in config.yaml
- Review logs in `ml_scorer.log`

### Feature mismatch errors
- Ensure feature engineering matches training code
- Check for missing columns in visit data
- Verify categorical encoding is consistent

## Future Enhancements

- [ ] Real-time API with FastAPI service
- [ ] Multi-label classification (14d, 30d, 90d)
- [ ] Deep learning models (Neural Networks)
- [ ] Automated model retraining pipeline
- [ ] SHAP explanation dashboard
- [ ] A/B testing framework
- [ ] Export predictions to CSV/Excel
- [ ] Email/SMS alerts for high-risk patients

## Support

For issues or questions:
1. Check logs: `ml_scorer.log`
2. Review model metrics in database
3. Verify data quality in input CSVs
4. Consult this README and code comments

---

**Version:** v0.1.0  
**Last Updated:** 2025-01-XX  
**Author:** HealthBase Development Team

