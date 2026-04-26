# Quick Setup Guide - ML Risk Prediction Module

## 🚀 Fast Track to Deployment

### Step 1: Database Setup (5 minutes)

```bash
# Run the SQL schema
mysql -u root -p healthbase < ml_module/database_schema.sql
```

This creates 5 tables needed for ML functionality.

### Step 2: Train the Model (1 hour - in Colab)

1. Go to [Google Colab](https://colab.research.google.com)
2. File → Upload → Upload `ml_module/ml_model_training.ipynb`
3. Upload your data files:
   - `mmc_opd_visits_2023_2025.csv`
   - `mmc_monthly_kpis_2023_2025.csv`
4. Run all cells (Runtime → Run All)
5. Download the artifacts:
   - `model.pkl`
   - `feature_list.json`
   - `threshold.json`
6. Upload these to your server in the `ml_module/` folder

### Step 3: Install Python Dependencies (5 minutes)

```bash
# On your server
pip install pandas numpy scikit-learn xgboost pymysql pyyaml
# Or using requirements.txt
pip install -r ml_module/requirements.txt
```

### Step 4: Configure Database (2 minutes)

Edit `ml_module/config.yaml`:

```yaml
db:
  host: 'localhost'
  user: 'root'
  password: 'your_password'
  database: 'healthbase'
```

### Step 5: Test Batch Scoring (5 minutes)

```bash
# Test with CSV data
cd /path/to/project
python ml_module/batch_scorer.py --all

# Check results
mysql -u root -p healthbase -e "SELECT COUNT(*) FROM ml_predictions;"
mysql -u root -p healthbase -e "SELECT COUNT(*) FROM followup_queue;"
```

### Step 6: Set Up Daily Automation (2 minutes)

```bash
# Edit crontab
crontab -e

# Add this line (runs daily at 1 AM)
0 1 * * * cd /xampp/htdocs/hb && /usr/bin/python3 ml_module/batch_scorer.py
```

### Step 7: Access the Dashboard

Open in browser:
```
http://localhost/hb/assistant_view/ml_dashboard.php
```

## ✅ Verification Checklist

- [ ] Database tables created (`ml_models`, `ml_predictions`, etc.)
- [ ] Model artifacts uploaded (`model.pkl`, `feature_list.json`, `threshold.json`)
- [ ] Python dependencies installed
- [ ] `config.yaml` configured with database credentials
- [ ] Batch scoring runs without errors
- [ ] Predictions appear in `ml_predictions` table
- [ ] Follow-up queue gets populated
- [ ] Dashboard loads and displays data
- [ ] Cron job is set up

## 🧪 Quick Test

Run this to verify everything works:

```bash
# 1. Check model is registered
mysql -u root -p healthbase -e "SELECT * FROM ml_models WHERE deployed=1;"

# 2. Run batch scoring
python ml_module/batch_scorer.py --since 2025-01-01

# 3. Check predictions
mysql -u root -p healthbase -e "SELECT COUNT(*) as predictions, AVG(score) as avg_score FROM ml_predictions;"

# 4. Check follow-up queue
mysql -u root -p healthbase -e "SELECT COUNT(*) as pending FROM followup_queue WHERE status='Pending';"
```

## 🎯 Expected Results

After running batch scoring, you should see:
- Predictions in `ml_predictions` table
- High-risk patients (10-20% of total)
- Follow-up queue entries for high-risk patients
- Dashboard showing trends and statistics

## 🐛 Troubleshooting

### "No module named pymysql"
```bash
pip install pymysql
```

### "No deployed model found"
```sql
-- Manually insert a model record
INSERT INTO ml_models (model_version, threshold, feature_version) 
VALUES ('v0.1.0', 0.47, 'v1');
UPDATE ml_models SET deployed = 1 WHERE model_version = 'v0.1.0';
```

### Dashboard shows no data
- Check database connection in `config.yaml`
- Verify predictions exist: `SELECT * FROM ml_predictions LIMIT 5;`
- Check PHP error logs

### Batch scoring fails
```bash
# Run with verbose output
python ml_module/batch_scorer.py --all --verbose

# Check for missing columns in data
python -c "import pandas as pd; df = pd.read_csv('mmc_opd_visits_2023_2025.csv'); print(df.columns.tolist())"
```

## 📚 Next Steps

1. **Monitor performance** - Check dashboard daily
2. **Retrain quarterly** - Run Colab notebook with new data
3. **Fine-tune threshold** - Adjust in `config.yaml` based on recall/precision
4. **Train staff** - Show them how to use the dashboard and follow-up queue

## 🎓 Resources

- Full documentation: `ml_module/README.md`
- Implementation summary: `ML_RISK_PREDICTION_IMPLEMENTATION.md`
- Colab training notebook: `ml_module/ml_model_training.ipynb`

---

**Ready to deploy! 🚀**

