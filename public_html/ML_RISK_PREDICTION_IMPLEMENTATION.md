# ML Risk Prediction Module - Implementation Summary

**Objective 3:** Risk Prediction and Patient Progress Monitoring Module

## 📋 Overview

This document outlines the complete implementation of the ML Risk Prediction Module that uses machine learning to identify patients at risk of early return visits or worsening health conditions.

## ✅ Implementation Status

### Completed Components

#### 1. Database Schema ✅
**File:** `ml_module/database_schema.sql`

Created 5 new tables:
- `ml_models` - Stores model metadata, metrics, and deployment status
- `ml_predictions` - Stores predictions for each visit with risk scores and tiers
- `followup_queue` - Automated follow-up recommendations for high-risk patients
- `patient_health_trends` - Aggregated health trends (Improving/Stable/At-Risk)
- `audit_ml` - Audit log for all ML operations

#### 2. Model Training Notebook ✅
**File:** `ml_module/ml_model_training.ipynb`

Google Colab notebook with:
- Data loading and EDA
- Feature engineering (20+ features including vitals, diagnosis, temporal, etc.)
- Time-aware train/val/test split (2023-2024 train, 2025 validation, 2025 H2 test)
- Model training (Logistic Regression + XGBoost)
- Threshold optimization (target: 70% recall, 10-20% high-risk coverage)
- SHAP explainability for feature importance
- Model artifact export (model.pkl, feature_list.json, threshold.json)

**Features extracted:**
- Categorical: diagnosis_group, service_type, sex, chronic_flag, visit_session, provider_specialty
- Numeric: age, systolic_bp, diastolic_bp, heart_rate, bmi, prior_visits_90d, wait_time_minutes
- Temporal: day_of_week, month, rainy_season, cool_season
- Special: pwd_flag

#### 3. Batch Scoring Script ✅
**File:** `ml_module/batch_scorer.py`

Python CLI tool for batch scoring:
- Fetches visits from database or CSV
- Builds features using deterministic function (matches training)
- Loads trained model and generates predictions
- Writes to `ml_predictions` table
- Generates follow-up queue entries for high-risk patients
- Supports `--since DATE`, `--all`, `--config FILE`

**Usage:**
```bash
python batch_scorer.py --since 2025-01-01
python batch_scorer.py --all  # For testing with CSV
```

#### 4. PHP Dashboard ✅
**File:** `assistant_view/ml_dashboard.php`

Comprehensive ML dashboard with:
- Real-time statistics (total predictions, high-risk count, avg score)
- 30-day risk trend chart (Chart.js)
- High-risk patients table with actionable buttons
- Follow-up queue management (priority scheduling, status updates)
- Model version display and metrics

**Features:**
- View patient details
- Schedule follow-up appointments
- Mark follow-ups as completed
- Filter by priority level

#### 5. API Endpoint ✅
**File:** `assistant_view/api/ml_score.php`

Real-time scoring API:
- POST endpoint for immediate risk assessment
- Fallback rule-based scoring when ML unavailable
- Auto-generates follow-ups for high-risk patients
- Returns JSON with score, risk_tier, threshold, model_version

**Example:**
```bash
curl -X POST http://localhost/api/ml_score.php \
  -H "Content-Type: application/json" \
  -d '{"visit_id": "abc-123"}'
```

#### 6. Patient History Integration ✅
**File:** `appointments/patient_history.php`

Enhanced patient history view with:
- ML risk scores for each visit
- Risk tier visualization (badges)
- Health trend status (Improving/Stable/At-Risk)
- Aggregated statistics (visit count, avg risk score)
- All visit history with diagnoses

#### 7. Configuration & Documentation ✅
**Files:** 
- `ml_module/config.yaml` - Database config, thresholds, alert settings
- `ml_module/requirements.txt` - Python dependencies
- `ml_module/README.md` - Complete setup and usage guide

## 🎯 Implementation of Sub-objectives

### 3.1: ML Models Trained on Anonymized Data ✅
- ✅ Utilizes anonymized patient visit data
- ✅ Classifies health trends as Improving/Stable/At-Risk
- ✅ Multiple model options (LR, XGBoost)
- ✅ Time-aware splitting prevents data leakage
- ✅ SHAP explainability for transparency

### 3.2: Progress Monitoring Dashboard ✅
- ✅ Dashboard displays recent consultations
- ✅ Shows diagnoses with ML risk status
- ✅ Health trend status with visual indicators
- ✅ Patient-specific risk history
- ✅ Aggregated statistics per patient

### 3.3: Automated Alerts for At-Risk Patients ✅
- ✅ Automatic detection of high-risk patients
- ✅ Scores above threshold trigger alerts
- ✅ Follow-up queue with 7-14 day recommendations
- ✅ Priority levels (Priority for high score or PWD)
- ✅ Doctor/staff notifications via queue system

### 3.4: Risk Summary Reports ✅
- ✅ Follow-up queue serves as risk report
- ✅ Filterable by priority and status
- ✅ Export capabilities (via API)
- ✅ Trend charts for visualization
- ✅ Audit log for all actions

## 📊 Model Performance Targets

The implementation targets:

- **Recall ≥ 70%** - Catch at least 70% of actual returns
- **Precision ≥ 40%** - At least 40% of flagged patients return
- **High-Risk Coverage: 10-20%** - Manageable alert volume
- **AUROC ≥ 0.65** - Baseline performance acceptable

## 🔄 Workflow

### Data Flow

```
1. Visit Created → opd_visits table
2. ML Service → Extract features → Score using model
3. Write Prediction → ml_predictions table
4. If High-Risk → Generate follow-up → followup_queue
5. Dashboard → Display predictions and queue
6. Staff Action → Schedule or dismiss follow-up
```

### Daily Batch Scoring

```bash
# Via cron job (1 AM daily)
0 1 * * * cd /path/to/project && python ml_module/batch_scorer.py

# Or manually
python ml_module/batch_scorer.py --since 2025-01-15
```

## 🎨 User Interface

### Dashboard Features

1. **KPI Cards** - Total predictions, high-risk count, avg score
2. **Trend Chart** - 30-day line chart showing total vs high-risk visits
3. **High-Risk Patients Table** - Sortable, actionable patient list
4. **Follow-up Queue** - Priority-ordered with scheduling actions
5. **Model Badge** - Current deployed model version

### Patient History Features

1. **Patient Header** - Name, ID, health status badge
2. **Statistics Grid** - Age, recent visits, avg risk score
3. **Risk Predictions** - Historical ML scores with tiers
4. **Visit Timeline** - Complete history with diagnoses

## 🔐 Security & Privacy

- ✅ Anonymized patient data only
- ✅ No PHI in logs or predictions
- ✅ Audit trail for all ML operations
- ✅ Role-based access (assistants/doctors)
- ✅ Secure database connections

## 🚀 Deployment Steps

### 1. Database Setup
```bash
mysql -u root -p healthbase < ml_module/database_schema.sql
```

### 2. Model Training
- Upload CSV files to Colab
- Run `ml_model_training.ipynb`
- Download artifacts (model.pkl, feature_list.json, threshold.json)
- Upload to server

### 3. Install Dependencies
```bash
pip install -r ml_module/requirements.txt
```

### 4. Configure
Edit `config.yaml` with database credentials

### 5. Test Scoring
```bash
python ml_module/batch_scorer.py --all
```

### 6. Set Up Automation
```bash
crontab -e
# Add: 0 1 * * * cd /path && python ml_module/batch_scorer.py
```

### 7. Access Dashboard
Visit: `http://yourdomain/assistant_view/ml_dashboard.php`

## 📝 Testing

### Test Cases

1. **Model Training** - Run Colab notebook, verify artifacts created
2. **Batch Scoring** - Score test dataset, check ml_predictions table
3. **API Endpoint** - POST with visit_id, verify JSON response
4. **Dashboard** - Load page, verify all sections render
5. **Follow-up Queue** - Verify high-risk patients appear, test actions
6. **Patient History** - View ML predictions integrated with history

## 🔧 Maintenance

### Model Retraining

Retrain quarterly or when:
- Performance degrades (< 0.65 AUROC)
- New data available (6+ months)
- New features needed

### Monitoring

Check logs:
```bash
tail -f ml_module/ml_scorer.log
```

Database queries:
```sql
-- Check prediction distribution
SELECT risk_tier, COUNT(*) 
FROM ml_predictions 
GROUP BY risk_tier;

-- Check follow-up queue size
SELECT COUNT(*) FROM followup_queue WHERE status = 'Pending';

-- View model performance
SELECT * FROM ml_models WHERE deployed = 1;
```

## 📈 Future Enhancements

- [ ] Real-time FastAPI microservice
- [ ] Multi-label prediction (14d, 30d, 90d)
- [ ] Deep learning models
- [ ] Automated retraining pipeline
- [ ] SHAP explanation viewer
- [ ] Email/SMS notifications
- [ ] A/B testing framework
- [ ] Model performance dashboard

## 📚 Files Created

```
ml_module/
├── database_schema.sql          # 5 new database tables
├── ml_model_training.ipynb      # Colab training notebook
├── batch_scorer.py              # Python CLI scoring tool
├── config.yaml                  # Configuration
├── requirements.txt             # Python dependencies
└── README.md                    # Complete documentation

assistant_view/
├── ml_dashboard.php             # ML monitoring dashboard
└── api/ml_score.php             # Real-time scoring API

appointments/
└── patient_history.php         # Enhanced history with ML data
```

## 🎉 Summary

The ML Risk Prediction Module is **fully implemented** with:

✅ Database schema for ML data
✅ Complete Colab training notebook
✅ Batch scoring pipeline with CLI
✅ PHP dashboard for monitoring
✅ Real-time API endpoint
✅ Patient history integration
✅ Automated follow-up queue
✅ Comprehensive documentation

**Status: READY FOR DEPLOYMENT**

---

**Next Steps:**
1. Run the Colab notebook to train model
2. Upload artifacts to server
3. Set up cron job for daily scoring
4. Train staff on dashboard usage
5. Monitor performance and iterate

