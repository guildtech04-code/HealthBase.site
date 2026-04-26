# ⚡ ML Risk Prediction - Quick Start Checklist

## 🎯 Goal
Implement the ML Risk Prediction Module in 1-2 hours

---

## 📝 PRE-START CHECKLIST

Before you begin, make sure you have:
- [ ] XAMPP installed and running
- [ ] Python 3.8+ installed
- [ ] Google account (for Colab)
- [ ] CSV files ready (`mmc_opd_visits_2023_2025.csv`)
- [ ] 1-2 hours of time
- [ ] Internet connection

---

## 🚀 QUICK START (15 Steps)

### PART 1: Database Setup (5 min)

**Step 1:** Open phpMyAdmin
```
http://localhost/phpmyadmin
```
- [ ] Logged in

**Step 2:** Select database
- [ ] Selected `healthbase` database (or created it)

**Step 3:** Import SQL schema
- [ ] Click "Import" tab
- [ ] Choose `ml_module/database_schema.sql`
- [ ] Click "Go"

**Step 4:** Verify tables
```sql
SHOW TABLES LIKE 'ml_%';
```
- [ ] 5 tables created (ml_models, ml_predictions, followup_queue, etc.)

✅ **PART 1 DONE!**

---

### PART 2: Train Model in Colab (20 min)

**Step 5:** Go to Colab
```
https://colab.research.google.com
```
- [ ] Signed in

**Step 6:** Upload notebook
- [ ] File → Upload → `ml_module/ml_model_training.ipynb`
- [ ] Uploaded successfully

**Step 7:** Upload data files
- [ ] Click folder icon (📁)
- [ ] Upload `mmc_opd_visits_2023_2025.csv`
- [ ] Upload `mmc_monthly_kpis_2023_2025.csv`
- [ ] Both files show green checkmarks

**Step 8:** Install packages
- [ ] Created new cell
- [ ] Ran: `!pip install pandas numpy scikit-learn xgboost shap`
- [ ] No errors shown

**Step 9:** Run training
- [ ] Click: Runtime → Run all
- [ ] Waited 10-15 minutes
- [ ] Saw "Saved: model.pkl" messages

✅ **PART 2 DONE!**

---

### PART 3: Download Model Files (2 min)

**Step 10:** Download artifacts
- [ ] Right-click `model.pkl` → Download
- [ ] Right-click `feature_list.json` → Download
- [ ] Right-click `threshold.json` → Download
- [ ] Saved to: `C:\xampp\htdocs\hb\ml_module\`

**Step 11:** Verify files
- [ ] Opened Windows Explorer
- [ ] Checked `ml_module` folder
- [ ] All 3 files present

✅ **PART 3 DONE!**

---

### PART 4: Install Dependencies (2 min)

**Step 12:** Install Python packages
```powershell
pip install pandas numpy scikit-learn xgboost pymysql pyyaml
```
- [ ] Ran command
- [ ] No errors
- [ ] Packages installed

✅ **PART 4 DONE!**

---

### PART 5: Configure (1 min)

**Step 13:** Edit config.yaml
- [ ] Opened `ml_module/config.yaml`
- [ ] Set password: '' (empty for XAMPP)
- [ ] Saved file

✅ **PART 5 DONE!**

---

### PART 6: Test (5 min)

**Step 14:** Run batch scoring
```powershell
cd C:\xampp\htdocs\hb\ml_module
python batch_scorer.py --all
```
- [ ] Ran command
- [ ] Saw "Batch scoring complete!" message
- [ ] No errors

**Step 15:** Verify in database
```sql
SELECT COUNT(*) FROM ml_predictions;
SELECT COUNT(*) FROM followup_queue;
```
- [ ] Predictions table has data
- [ ] Follow-up queue has entries

✅ **PART 6 DONE!**

---

### BONUS: Setup Automation (5 min)

**Step 16:** Create Windows Task
- [ ] Opened Task Scheduler
- [ ] Created new task: "ML Batch Scorer"
- [ ] Set trigger: Daily at 2:00 AM
- [ ] Set action: Python + batch_scorer.py
- [ ] Saved task

✅ **AUTOMATION DONE!**

---

## 🎉 SUCCESS! NOW ACCESS DASHBOARD

### Step 17: Open Dashboard
```
http://localhost/hb/assistant_view/ml_dashboard.php
```

### What You'll See:
- [ ] Dashboard loads
- [ ] Stats cards show data
- [ ] Trend chart displays
- [ ] High-risk patients listed
- [ ] Follow-up queue populated

### Test Actions:
- [ ] Click "View" on a patient
- [ ] Click "Follow-up" on a patient
- [ ] Click "Schedule" in queue
- [ ] Chart shows trends

---

## ✅ FINAL VERIFICATION

```powershell
# 1. Check model files exist
dir C:\xampp\htdocs\hb\ml_module\*.pkl
dir C:\xampp\htdocs\hb\ml_module\*.json

# 2. Check database has data
mysql -u root -p healthbase -e "SELECT COUNT(*) as preds FROM ml_predictions;"
mysql -u root -p healthbase -e "SELECT COUNT(*) as queue FROM followup_queue;"

# 3. Test scoring works
python ml_module/batch_scorer.py --since 2025-01-01
```

Expected results:
- ✅ model.pkl exists
- ✅ Predictions table has data
- ✅ Follow-up queue has entries
- ✅ Dashboard shows information

---

## 🎓 WHAT TO DO NEXT

### Immediate Actions:
1. ✅ Explore the dashboard
2. ✅ Review high-risk patients
3. ✅ Check follow-up queue
4. ✅ Test all buttons

### This Week:
1. 📊 Monitor dashboard daily
2. 👥 Train staff on usage
3. 📈 Review trends
4. 🔔 Check for alerts

### This Month:
1. 📋 Generate reports
2. 🔄 Fine-tune threshold if needed
3. 📊 Analyze model performance
4. 🎯 Set follow-up completion goals

### Quarterly:
1. 🔄 Retrain model with new data
2. 📈 Update feature set
3. 🎯 Adjust thresholds
4. 📊 Compare new vs old performance

---

## 🐛 TROUBLESHOOTING GUIDE

### No model.pkl file?
→ Go back to Step 9 and re-run all cells in Colab

### "Module not found" error?
→ Run: `pip install <module_name>`

### Dashboard shows "No data"?
→ Run batch scoring: `python batch_scorer.py --all`

### Database connection error?
→ Check `config.yaml` has correct password

### Predictions table empty?
→ Re-run Step 14 (batch scoring)

---

## 📚 DOCUMENTATION INDEX

- **This File:** Quick checklist for setup
- **STEP_BY_STEP_ML_GUIDE.md:** Detailed walkthrough (15 pages)
- **ml_module/README.md:** Technical documentation
- **SETUP_INSTRUCTIONS.md:** Fast setup guide
- **ML_RISK_PREDICTION_IMPLEMENTATION.md:** Complete implementation summary

---

## 🎉 CONGRATULATIONS!

You've completed:
- ✅ Database setup
- ✅ Model training
- ✅ Artifact download
- ✅ Dependency installation
- ✅ Configuration
- ✅ Testing
- ✅ Automation
- ✅ Dashboard access

**Your ML Risk Prediction Module is LIVE!** 🚀

---

## 📞 GETTING STUCK?

1. Check the error message
2. Review the corresponding step above
3. Consult detailed guide: `STEP_BY_STEP_ML_GUIDE.md`
4. Look in Troubleshooting section
5. Verify each checkbox is complete

**Remember:** Most issues are solved by:
- Running batch scoring again
- Checking database connection
- Verifying files are in correct location

---

**Time Spent:** ~45-60 minutes
**Difficulty:** ⭐⭐⭐ (Moderate)
**Status:** 🎉 **COMPLETE!**

