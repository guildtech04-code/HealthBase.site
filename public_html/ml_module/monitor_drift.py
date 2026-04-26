#!/usr/bin/env python3
import json
import sys
from datetime import datetime, timedelta
import pandas as pd

try:
    import pymysql
except ImportError:
    print("pymysql not installed")
    sys.exit(1)

import yaml

def load_config(path='config.yaml'):
    with open(path, 'r') as f:
        return yaml.safe_load(f)

def get_conn(cfg):
    return pymysql.connect(
        host=cfg['db']['host'],
        user=cfg['db']['user'],
        password=cfg['db']['password'],
        database=cfg['db']['database'],
        charset='utf8mb4'
    )

def main():
    cfg = load_config()
    conn = get_conn(cfg)
    cursor = conn.cursor()

    since = (datetime.now() - timedelta(days=14)).strftime('%Y-%m-%d')
    # Pull last 14 days of predictions
    query = """
      SELECT scored_at, score, risk_tier
      FROM ml_predictions
      WHERE scored_at >= %s
      ORDER BY scored_at DESC
    """
    cursor.execute(query, (since,))
    rows = cursor.fetchall()
    df = pd.DataFrame(rows, columns=['scored_at','score','risk_tier'])
    if df.empty:
        print("No recent predictions found")
        return

    df['date'] = pd.to_datetime(df['scored_at']).dt.date
    agg = df.groupby('date').agg(
        total=('score','count'),
        avg_score=('score','mean'),
        high_pct=(lambda x: (df.loc[df['date']==x.name,'risk_tier']=='High').mean())
    )

    # Percent high-risk per day
    daily_high = df.groupby('date').apply(lambda g: (g['risk_tier']=='High').mean()).rename('high_rate')
    daily = pd.concat([agg[['total','avg_score']], daily_high], axis=1)

    # Compare last 7 vs previous 7 days
    last7 = daily.tail(7)
    prev7 = daily.iloc[-14:-7] if len(daily) >= 14 else pd.DataFrame()

    drift_flag = False
    drift_msg = ''
    if not prev7.empty:
        last_high = last7['high_rate'].mean()
        prev_high = prev7['high_rate'].mean()
        change = (last_high - prev_high) / max(prev_high, 1e-6)
        if abs(change) > cfg['alerts'].get('drift_threshold', 0.20):
            drift_flag = True
            drift_msg = f"High-risk rate changed by {change:.1%} (prev {prev_high:.1%} → last {last_high:.1%})"

    payload = {
        'last7': last7.reset_index().to_dict(orient='records'),
        'prev7': prev7.reset_index().to_dict(orient='records'),
        'drift': drift_msg,
        'generated_at': datetime.now().isoformat()
    }

    # Write to audit_ml
    audit_sql = "INSERT INTO audit_ml (action, actor, payload_json) VALUES (%s, %s, %s)"
    cursor.execute(audit_sql, ("drift_check", "monitor_drift", json.dumps(payload)))
    conn.commit()
    cursor.close()
    conn.close()

    print("Drift check complete.")
    if drift_flag:
        print(drift_msg)

if __name__ == '__main__':
    main()


