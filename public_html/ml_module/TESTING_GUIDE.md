# ML Prediction Testing Guide

This guide helps you test the ML prediction system when you don't have real patient data yet.

## Quick Start

### Step 1: Generate Test Data

Run the test data generator script from your web browser or command line:

**Option A: Via Web Browser**
1. Navigate to: `https://healthbase.site/ml_module/generate_test_data.php`
2. The script will create sample appointments, consultations, and OPD visit records

**Option B: Via Command Line (SSH)**
```bash
cd /home/u654420946/domains/healthbase.site/public_html/ml_module
php generate_test_data.php
```

### Step 2: Run Batch Scorer

After generating test data, run the ML batch scorer to generate predictions:

```bash
cd /home/u654420946/domains/healthbase.site/public_html/ml_module
source venv/bin/activate
export OPENBLAS_NUM_THREADS=1
export OMP_NUM_THREADS=1
python batch_scorer.py --since 2024-01-01
```

Or to score ALL visits:
```bash
python batch_scorer.py --all
```

### Step 3: View Predictions in Dashboard

1. Navigate to: `https://healthbase.site/assistant_view/ml_dashboard.php`
2. You should see:
   - Recent consultations with health trends
   - Patient progress snapshots
   - Patients requiring attention
   - Follow-up queue

## What the Test Data Generator Creates

The `generate_test_data.php` script creates:

1. **Appointments** (last 60 days)
   - 2-4 appointments per weekday
   - Mix of "Completed" and "Confirmed" statuses
   - Random times between 9 AM - 4 PM

2. **Consultations** (80% of completed appointments)
   - Random diagnoses from common conditions
   - Follow-up dates 7-30 days out
   - Consultation notes and treatment plans

3. **OPD Visits Table** (for ML batch scorer)
   - Auto-creates `opd_visits` table if missing
   - Populates with visit records including:
     - Patient demographics (age, gender)
     - Vital signs (BP, heart rate, BMI)
     - Diagnosis groups
     - Chronic condition flags
     - Visit timing and session

## Test Data Features

### Patient Variety
- Uses your existing patients and doctors
- Creates appointments for different patients over time
- Varied diagnoses and conditions

### Realistic Vitals
- **Blood Pressure**: 100-160/60-100 (systolic/diastolic)
- **Heart Rate**: 60-120 bpm
- **BMI**: 18-36 kg/m²
- **Wait Time**: 10-50 minutes

### Timeline
- Data spans last 60 days
- Weekends excluded (adjustable)
- Progressive dates so predictions can show trends

## Verification Checklist

After running the test data generator and batch scorer, verify:

- [ ] `opd_visits` table has records
- [ ] `ml_predictions` table has predictions (check with: `SELECT COUNT(*) FROM ml_predictions`)
- [ ] `followup_queue` has high-risk patients (check with: `SELECT COUNT(*) FROM followup_queue WHERE status='Pending'`)
- [ ] Dashboard shows consultation data
- [ ] Health trend badges display (Improving, Stable, Needs Attention)
- [ ] Follow-up queue shows patients with suggested dates

## Manual Testing Queries

Check prediction results in database:

```sql
-- Count predictions
SELECT COUNT(*) as total_predictions,
       SUM(CASE WHEN risk_tier = 'High' THEN 1 ELSE 0 END) as high_risk_count
FROM ml_predictions;

-- View recent predictions
SELECT patient_id, score, risk_tier, scored_at
FROM ml_predictions
ORDER BY scored_at DESC
LIMIT 10;

-- Check follow-up queue
SELECT fq.*, p.first_name, p.last_name
FROM followup_queue fq
LEFT JOIN patients p ON fq.patient_id = p.id
WHERE fq.status = 'Pending'
ORDER BY fq.suggested_date ASC;
```

## Troubleshooting

### "No patients found" error
- Create at least one patient in the system first
- The script uses existing patients from your database

### "No doctors found" error
- Create at least one doctor user (role='doctor')
- The script uses existing doctors from your users table

### Batch scorer returns errors
1. Verify `opd_visits` table exists and has data
2. Check model files exist: `model.pkl`, `feature_list.json`, `threshold.json`
3. Verify database credentials in `config.yaml`
4. Check Python dependencies: `pip install -r requirements.txt`

### Dashboard shows "No data"
1. Verify predictions exist: `SELECT * FROM ml_predictions LIMIT 1`
2. Check appointment dates are recent (within last 60 days)
3. Verify consultations table has records linked to appointments

## Next Steps After Testing

Once testing is successful:

1. **Remove test data** (optional):
   ```sql
   DELETE FROM opd_visits WHERE visit_id LIKE 'APP%';
   DELETE FROM ml_predictions WHERE visit_id LIKE 'APP%';
   DELETE FROM followup_queue WHERE visit_id LIKE 'APP%';
   -- Keep appointments/consultations if you want to use them
   ```

2. **Set up daily cron job** for batch scoring:
   ```bash
   # Add to crontab
   0 2 * * * cd /home/u654420946/domains/healthbase.site/public_html/ml_module && /opt/alt/python311/bin/python3 batch_scorer.py --since $(date -d "yesterday" +\%Y-\%m-\%d) >> ml_scorer.log 2>&1
   ```

3. **Monitor predictions** weekly through the dashboard

## Sample Test Scenarios

### Test High-Risk Detection
- Generate data with high BP values (systolic > 140, diastolic > 90)
- Patients with chronic conditions (diabetes, hypertension)
- Multiple visits in short timeframes
- Check if predictions correctly flag these as "High Risk"

### Test Trend Calculation
- Create patient with 3 visits over 30 days
- First visit: Low risk (30%)
- Second visit: Medium risk (50%)
- Third visit: High risk (75%)
- Verify trend shows "Monitor Closely" or "Needs Attention"

### Test Follow-up Queue
- Generate high-risk predictions (score >= 0.59)
- Verify entries appear in follow-up queue
- Check suggested dates are 7-14 business days from visit
- Verify priority levels set correctly

---

**Need Help?** Check `ml_module/README.md` for detailed system documentation.


