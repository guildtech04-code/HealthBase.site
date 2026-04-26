# 🎯 Step-by-Step ML Risk Prediction Guide

## Complete Guide to Implementing Objective 3

---

## 📋 TABLE OF CONTENTS

1. [Database Setup](#step-1-database-setup)
2. [Google Colab Model Training](#step-2-google-colab-model-training)
3. [Download Model Artifacts](#step-3-download-model-artifacts)
4. [Install Python Dependencies](#step-4-install-python-dependencies)
5. [Configure Database Connection](#step-5-configure-database-connection)
6. [Test Batch Scoring](#step-6-test-batch-scoring)
7. [Set Up Daily Automation](#step-7-set-up-daily-automation)
8. [Access Dashboard](#step-8-access-dashboard)
9. [Troubleshooting](#troubleshooting)

---

## STEP 1: Database Setup

### What You'll Do:
Create the 5 database tables needed for ML functionality.

### Instructions:

1. **Open XAMPP Control Panel**
   - Click "Start" on Apache
   - Click "Start" on MySQL

2. **Open phpMyAdmin**
   - Go to: http://localhost/phpmyadmin
   - Select database: `healthbase` (or create it if it doesn't exist)

3. **Run the SQL Script**
   
   Option A - Using phpMyAdmin:
   - Click on "SQL" tab
   - Click "Import"
   - Choose file: `ml_module/database_schema.sql`
   - Click "Go"

   Option B - Using Command Line:
   ```bash
   # Open PowerShell in your project folder
   cd C:\xampp\htdocs\hb
   
   # Run the SQL file
   mysql -u root -p healthbase < ml_module/database_schema.sql
   ```

4. **Verify Tables Created**
   ```sql
   -- Run this query in phpMyAdmin
   SHOW TABLES LIKE 'ml_%';
   ```
   
   You should see:
   - ml_models
   - ml_predictions
   - followup_queue
   - patient_health_trends
   - audit_ml

✅ **Checkpoint:** If you see all 5 tables, proceed to Step 2!

---

## STEP 2: Google Colab Model Training

### What You'll Do:
Train the ML model using Google's free GPU resources.

### Instructions:

1. **Go to Google Colab**
   - Visit: https://colab.research.google.com
   - Sign in with your Google account

2. **Upload the Notebook**
   - Click: `File` → `Upload notebook`
   - Navigate to: `C:\xampp\htdocs\hb\ml_module\ml_model_training.ipynb`
   - Click "Open"

3. **Upload Your Data Files**
   - In the left sidebar, click the 📁 folder icon
   - Click "Upload to session storage" (cloud icon)
   - Upload these 2 files:
     - `mmc_opd_visits_2023_2025.csv`
     - `mmc_monthly_kpis_2023_2025.csv`
   
   Wait for upload to complete (green checkmarks will appear)

4. **Install Required Libraries**
   
   In Colab, create a new cell (click "+ Code" button) and run:
   ```python
   !pip install pandas numpy scikit-learn xgboost shap matplotlib seaborn
   ```
   
   Click the ▶️ play button to run

5. **Run All Cells**
   - Click: `Runtime` → `Run all`
   - OR press: `Ctrl + F9`
   
   **What Happens:**
   - Loads and analyzes data
   - Builds features (takes 2-3 minutes)
   - Trains models (takes 5-10 minutes)
   - Finds optimal threshold
   - Generates SHAP plots
   - Saves artifacts

6. **Wait for Completion**
   - Watch the output for errors
   - Should see messages like:
     ```
     Saved: model.pkl
     Saved: feature_list.json
     Saved: threshold.json
     Model artifacts ready for deployment!
     ```

✅ **Checkpoint:** If you see all 3 files saved successfully, proceed to Step 3!

**⏱️ Time Required:** 15-20 minutes total

---

## STEP 3: Download Model Artifacts

### What You'll Do:
Download the trained model files to your computer.

### Instructions:

1. **Download from Colab**
   
   In Colab, click the 📁 folder icon in the left sidebar
   
   You should see these 3 files:
   - `model.pkl` (the trained model)
   - `feature_list.json` (feature definitions)
   - `threshold.json` (model metrics and threshold)
   
   For each file:
   - Right-click → Download
   - Save to: `C:\xampp\htdocs\hb\ml_module\`

2. **Verify Files**
   
   Open Windows Explorer and navigate to:
   ```
   C:\xampp\htdocs\hb\ml_module\
   ```
   
   You should see:
   - ✅ model.pkl
   - ✅ feature_list.json
   - ✅ threshold.json
   
   If any file is missing, go back to Colab and re-run the last cell.

✅ **Checkpoint:** All 3 files should be in `ml_module/` folder!

---

## STEP 4: Install Python Dependencies

### What You'll Do:
Install Python packages needed for batch scoring.

### Instructions:

1. **Check Python Installation**
   
   Open PowerShell:
   ```powershell
   python --version
   ```
   
   Should show Python 3.8+ (if not, install from python.org)

2. **Install Dependencies**
   
   Open PowerShell in your project folder:
   ```powershell
   cd C:\xampp\htdocs\hb\ml_module
   
   pip install pandas numpy scikit-learn xgboost pymysql pyyaml
   ```
   
   Wait for all packages to install (2-3 minutes)

3. **Verify Installation**
   ```powershell
   python -c "import pandas, numpy, sklearn, xgboost, pymysql, yaml; print('All packages installed!')"
   ```
   
   Should print: "All packages installed!"

✅ **Checkpoint:** No error messages means success!

---

## STEP 5: Configure Database Connection

### What You'll Do:
Update the config file with your database credentials.

### Instructions:

1. **Open Config File**
   
   Navigate to:
   ```
   C:\xampp\htdocs\hb\ml_module\config.yaml
   ```
   
   Open with Notepad or VS Code

2. **Update Database Settings**
   
   Find this section and update:
   ```yaml
   db:
     host: 'localhost'
     user: 'root'
     password: ''          # Leave empty for XAMPP default
     database: 'healthbase'
   ```
   
   If you have a MySQL password, add it to the password field.

3. **Save the File**
   
   Press `Ctrl + S` to save

✅ **Checkpoint:** Config file saved with correct settings!

---

## STEP 6: Test Batch Scoring

### What You'll Do:
Run the batch scorer to verify everything works.

### Instructions:

1. **Open PowerShell in Project Folder**
   ```powershell
   cd C:\xampp\htdocs\hb\ml_module
   ```

2. **Run Batch Scoring with Test Data**
   ```powershell
   python batch_scorer.py --all
   ```
   
   **What This Does:**
   - Reads CSV file for testing
   - Builds features for each visit
   - Scores using ML model
   - Writes predictions to database
   - Generates follow-up queue
   
   **Expected Output:**
   ```
   ============================================================
   ML Batch Scorer - Risk Prediction Pipeline
   ============================================================
   
   1. Fetching visits...
      Found 13059 visits
   
   2. Building features...
      Feature matrix shape: (13059, XX)
   
   3. Generating predictions...
      Predictions: 13059
      High-risk: 1823 (13.9%)
   
   4. Saving predictions...
      Saved 13059 predictions to ml_predictions table
   
   5. Generating follow-up queue...
      Generated 1823 follow-up queue entries
   
   ============================================================
   Batch scoring complete!
   ============================================================
   ```

3. **Verify in Database**
   
   Open phpMyAdmin:
   - Navigate to `healthbase` database
   - Click `ml_predictions` table
   - Click "Browse"
   - You should see predictions with risk scores
   
   Click `followup_queue` table
   - You should see high-risk patients

✅ **Checkpoint:** If you see predictions in both tables, success!

---

## STEP 7: Set Up Daily Automation

### What You'll Do:
Configure Windows Task Scheduler to run daily batch scoring.

### Instructions:

1. **Open Task Scheduler**
   - Press `Win + R`
   - Type: `taskschd.msc`
   - Press Enter

2. **Create New Task**
   - Click "Create Task..." in the right sidebar
   - Name: `ML Batch Scorer`
   - Description: `Daily ML risk predictions`

3. **Set Trigger**
   - Click "Triggers" tab
   - Click "New"
   - "Begin the task": `On a schedule`
   - "Daily" at: `2:00 AM` (or any time you prefer)
   - Click "OK"

4. **Set Action**
   - Click "Actions" tab
   - Click "New"
   - "Action": `Start a program`
   - Program/script: `C:\Python3X\python.exe` (find your Python path)
   
   Add arguments:
   ```
   C:\xampp\htdocs\hb\ml_module\batch_scorer.py
   ```
   
   Start in:
   ```
   C:\xampp\htdocs\hb\ml_module
   ```
   - Click "OK"

5. **Set Conditions**
   - Click "Conditions" tab
   - Uncheck: "Start the task only if the computer is on AC power"
   - Click "OK"

6. **Save Task**
   - Enter Windows password if prompted
   - Task created!

✅ **Checkpoint:** Task appears in Task Scheduler Library!

**Alternative: Manual Cron (for testing)**
   
   You can also run manually:
   ```powershell
   python C:\xampp\htdocs\hb\ml_module\batch_scorer.py
   ```

---

## STEP 8: Access Dashboard

### What You'll Do:
View the ML dashboard and verify everything is working.

### Instructions:

1. **Start XAMPP**
   - Open XAMPP Control Panel
   - Start Apache
   - Start MySQL

2. **Open Dashboard**
   
   Go to browser:
   ```
   http://localhost/hb/assistant_view/ml_dashboard.php
   ```

3. **What You Should See:**
   
   **Dashboard Page:**
   - Header: "🔬 ML Risk Prediction Dashboard"
   - Model version badge (e.g., "Model: v0.1.0")
   
   **Stats Cards (Top):**
   - Total Predictions Today
   - High-Risk Patients count
   - Average Risk Score
   
   **Risk Trends Chart:**
   - Line graph showing last 30 days
   - Blue line (total predictions)
   - Red line (high-risk count)
   
   **High-Risk Patients Table:**
   - List of patients with High risk tier
   - Risk scores and visit counts
   - "View" and "Follow-up" buttons
   
   **Follow-up Queue Table:**
   - Priority-level indicator
   - Suggested follow-up dates
   - Reason for follow-up
   - "Schedule" and "Complete" buttons

4. **Test Functions:**
   
   - Click "View" to see patient details
   - Click "Follow-up" to add to queue
   - Click "Schedule" to create appointment
   - Click "Complete" to mark as done

✅ **Checkpoint:** Dashboard loads and shows data!

---

## 🎨 VISUAL SUCCESS CHECKLIST

After completing all steps, verify:

```
✅ Database: 5 tables created
✅ Colab: Model artifacts saved
✅ Python: Dependencies installed
✅ Config: Database connection set
✅ Batch: Scoring runs successfully
✅ Database: Predictions exist
✅ Task: Daily automation set up
✅ Dashboard: Page loads with data
✅ Queue: Follow-ups generated
✅ Charts: Trends display correctly
```

---

## 🐛 TROUBLESHOOTING

### Issue: "No module named 'pymysql'"
**Solution:**
```powershell
pip install pymysql
```

### Issue: "No deployed model found"
**Solution:**
Run this SQL in phpMyAdmin:
```sql
INSERT INTO ml_models (model_version, threshold, feature_version, deployed) 
VALUES ('v0.1.0', 0.47, 'v1', 1);
```

### Issue: Dashboard shows "No data"
**Solutions:**
1. Check if predictions exist:
   ```sql
   SELECT COUNT(*) FROM ml_predictions;
   ```
2. Run batch scoring manually
3. Refresh the dashboard

### Issue: Batch scoring fails with "KeyError"
**Solution:**
- Ensure all columns exist in CSV
- Check `ml_module/config.yaml` is correct
- Verify Python version is 3.8+

### Issue: Can't upload to Colab
**Solution:**
- Use Google Drive: Upload CSV to Drive, then mount Drive in Colab
- Add this cell before uploading:
```python
from google.colab import drive
drive.mount('/content/drive')
```

### Issue: Model file is too large
**Solution:**
- `model.pkl` should be 5-50 MB
- If it's too large, retrain with fewer features
- Or use pickle with compression

### Issue: Task Scheduler won't run
**Solution:**
1. Test Python path:
```powershell
where python
```
2. Use full path in Task Scheduler
3. Run task manually to see errors

---

## 📊 WHAT TO EXPECT

### After Step 6 (Batch Scoring):
- ~13,000 predictions in `ml_predictions` table
- ~2,000 high-risk patients (10-15%)
- ~2,000 follow-ups in `followup_queue`

### Daily After Automation:
- New predictions added daily
- High-risk alerts generated
- Follow-up queue updated
- Dashboard reflects latest trends

### Dashboard Metrics:
- **Total Predictions**: Grows daily
- **High-Risk %**: 10-20% typically
- **Average Score**: 0.3-0.5 typically
- **Follow-up Queue**: 10-30 pending entries

---

## 🎓 NEXT STEPS AFTER DEPLOYMENT

1. **Train Staff:**
   - Show dashboard navigation
   - Explain risk tiers
   - Teach follow-up queue management

2. **Monitor Performance:**
   - Check dashboard daily
   - Review high-risk patients
   - Track follow-up completion rates

3. **Fine-Tune Model:**
   - Adjust threshold in `config.yaml`
   - Retrain with new data quarterly
   - Add custom rules as needed

4. **Generate Reports:**
   - Export predictions to CSV
   - Create monthly KPI summaries
   - Share insights with doctors

---

## 📞 NEED HELP?

Check these resources:
- Setup Guide: `ml_module/SETUP_INSTRUCTIONS.md`
- Full Docs: `ml_module/README.md`
- Implementation: `ML_RISK_PREDICTION_IMPLEMENTATION.md`

Or contact support with:
- Error messages
- Step where stuck
- Screenshots of issues

---

## ✅ FINAL VERIFICATION

Run this complete check:

```powershell
# Check Python
python --version

# Check model files
dir C:\xampp\htdocs\hb\ml_module\*.pkl

# Run test
cd C:\xampp\htdocs\hb\ml_module
python batch_scorer.py --all

# Check database
mysql -u root -p healthbase -e "SELECT COUNT(*) FROM ml_predictions;"
```

All checks should pass! 🎉

---

**🎉 CONGRATULATIONS!**

You've successfully implemented the ML Risk Prediction Module for Objective 3!

Your system now:
- ✅ Predicts patient risk automatically
- ✅ Monitors health trends
- ✅ Generates follow-up recommendations
- ✅ Provides actionable dashboards
- ✅ Runs daily automation

**Status: PRODUCTION READY** 🚀

