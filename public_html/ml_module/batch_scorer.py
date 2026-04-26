#!/usr/bin/env python3
"""
Batch Scorer for ML Risk Prediction
===================================

This script:
1. Reads recent visits from database or CSV
2. Builds features using deterministic feature engineering
3. Loads the trained model and scores visits
4. Writes predictions to ml_predictions table
5. Generates follow-up queue entries

Usage:
    python batch_scorer.py --since 2025-01-01
    python batch_scorer.py --all
    python batch_scorer.py --config config.yaml
"""

import argparse
import json
import pickle
import sys
from datetime import datetime, timedelta
from pathlib import Path
import pandas as pd
import numpy as np

try:
    import pymysql
    HAS_PYMYSQL = True
except ImportError:
    print("Warning: pymysql not installed. Database operations will not work.")
    HAS_PYMYSQL = False


class BatchScorer:
    def __init__(self, config_path='config.yaml'):
        """Initialize scorer with config."""
        self.config = self._load_config(config_path)
        self.model = self._load_model()
        self.feature_config = self._load_features()
        self.threshold = self.config.get('threshold', 0.47)
        
    def _load_config(self, config_path):
        """Load configuration file."""
        import yaml
        with open(config_path, 'r') as f:
            return yaml.safe_load(f)
    
    def _load_model(self):
        """Load trained model from pickle file."""
        model_path = Path(__file__).parent / 'model.pkl'
        with open(model_path, 'rb') as f:
            return pickle.load(f)
    
    def _load_features(self):
        """Load feature configuration."""
        feature_path = Path(__file__).parent / 'feature_list.json'
        with open(feature_path, 'r') as f:
            return json.load(f)
    
    def _get_db_connection(self):
        """Get database connection."""
        if not HAS_PYMYSQL:
            raise RuntimeError("pymysql not installed")
        
        db_config = self.config['db']
        return pymysql.connect(
            host=db_config['host'],
            user=db_config['user'],
            password=db_config['password'],
            database=db_config['database'],
            charset='utf8mb4'
        )
    
    def build_features(self, df):
        """
        Deterministic feature builder (must match training code).
        
        Args:
            df: DataFrame with visit data
            
        Returns:
            X: Feature matrix
        """
        df = df.copy()
        
        # 1. Temporal features
        df['visit_date'] = pd.to_datetime(df['visit_date'])
        df['visit_day_of_week'] = df['visit_date'].dt.dayofweek
        df['visit_month'] = df['visit_date'].dt.month
        df['is_weekend'] = (df['visit_day_of_week'] >= 5).astype(int)
        
        # 2. Seasonality flags
        df['is_rainy_season'] = df['visit_month'].isin([6, 7, 8, 9, 10]).astype(int)
        df['is_cool_season'] = df['visit_month'].isin([11, 12, 1, 2]).astype(int)
        
        # 3. Categorical encoding
        categorical_features = [
            'diagnosis_group', 'service_type', 'sex', 'chronic_flag',
            'visit_session', 'provider_specialty'
        ]
        
        df_encoded = pd.get_dummies(df[categorical_features], prefix=categorical_features)
        
        # 4. Numeric + derived features
        numeric_features = [
            'age', 'systolic_bp', 'diastolic_bp', 'heart_rate',
            'bmi', 'prior_visits_90d', 'wait_time_minutes'
        ]
        
        # Handle missing values
        for col in numeric_features:
            if df[col].isnull().sum() > 0:
                df[col] = df[col].fillna(df[col].median())

        # Derived features matching v2
        df['bp_level'] = (df['systolic_bp'] / df['diastolic_bp']).replace([np.inf, -np.inf], np.nan).fillna(2.0)
        # Age/BMI categories
        age_bins = pd.cut(df['age'], bins=[0, 18, 30, 50, 65, 120], labels=[0, 1, 2, 3, 4], include_lowest=True)
        df['age_group'] = age_bins.fillna(0).astype(int)
        bmi_bins = pd.cut(df['bmi'], bins=[0, 18.5, 25, 30, 200], labels=[0, 1, 2, 3], include_lowest=True)
        df['bmi_category'] = bmi_bins.fillna(1).astype(int)
        # Risk flags
        df['bp_high'] = ((df['systolic_bp'] >= 140) | (df['diastolic_bp'] >= 90)).astype(int)
        df['bp_low'] = (df['systolic_bp'] <= 90).astype(int)
        df['heart_rate_abnormal'] = ((df['heart_rate'] < 60) | (df['heart_rate'] > 100)).astype(int)
        df['bmi_abnormal'] = ((df['bmi'] < 18.5) | (df['bmi'] > 30)).astype(int)
        # Utilization flags (if prior_visits_90d exists)
        df['frequent_visitor'] = (df['prior_visits_90d'] >= 3).astype(int)
        df['very_frequent_visitor'] = (df['prior_visits_90d'] >= 5).astype(int)
        
        # Ensure all training features exist (align with dummy variables)
        # Assemble final numeric block
        extra_features = ['visit_day_of_week', 'visit_month', 'is_rainy_season', 'is_cool_season', 'is_weekend',
                          'bp_level', 'age_group', 'bmi_category', 'bp_high', 'bp_low', 'heart_rate_abnormal',
                          'bmi_abnormal', 'frequent_visitor', 'very_frequent_visitor', 'pwd_flag']
        existing_extras = [c for c in extra_features if c in df.columns]
        X_numeric = df[numeric_features + existing_extras]
        X = pd.concat([X_numeric, df_encoded], axis=1)
        
        # Reindex to match training feature order
        expected_features = self.feature_config['feature_names']
        X = X.reindex(columns=expected_features, fill_value=0)
        
        return X
    
    def fetch_visits(self, since_date=None, all_records=False):
        """
        Fetch visits from database or CSV.
        
        Returns:
            DataFrame with visit records
        """
        # Option 1: Read from CSV (for testing)
        if all_records:
            df = pd.read_csv('mmc_opd_visits_2023_2025.csv')
            df['visit_date'] = pd.to_datetime(df['visit_date'])
            return df
        
        # Option 2: Read from database
        if HAS_PYMYSQL:
            conn = self._get_db_connection()
            query = """
                SELECT 
                    visit_id, patient_id, visit_date, age, sex,
                    diagnosis_group, service_type, provider_specialty,
                    systolic_bp, diastolic_bp, heart_rate, bmi,
                    chronic_flag, prior_visits_90d, wait_time_minutes,
                    pwd_flag, visit_session
                FROM opd_visits
                WHERE visit_date >= %s
                ORDER BY visit_date DESC
            """
            params = [since_date] if since_date else [datetime.now() - timedelta(days=1)]
            
            df = pd.read_sql(query, conn, params=params)
            conn.close()
            return df
        else:
            raise RuntimeError("Cannot fetch from database without pymysql")
    
    def predict(self, X):
        """
        Generate risk predictions.
        
        Args:
            X: Feature matrix
            
        Returns:
            scores: Risk scores (0-1)
        """
        return self.model.predict_proba(X)[:, 1]
    
    def score_to_tier(self, score):
        """Convert score to risk tier."""
        return 'High' if score >= self.threshold else 'Low'
    
    def save_predictions(self, df_visits, scores, risk_tiers):
        """
        Save predictions to ml_predictions table.
        
        Args:
            df_visits: Original visit dataframe
            scores: Risk scores
            risk_tiers: Risk tier labels
        """
        if not HAS_PYMYSQL:
            print("Skipping database write (no pymysql)")
            return
        
        conn = self._get_db_connection()
        cursor = conn.cursor()
        
        model_version = self.config.get('model_version', 'v0.1.0')
        
        insert_sql = """
            INSERT INTO ml_predictions 
            (visit_id, patient_id, score, risk_tier, model_version, threshold, features_json)
            VALUES (%s, %s, %s, %s, %s, %s, %s)
            ON DUPLICATE KEY UPDATE
                score = VALUES(score),
                risk_tier = VALUES(risk_tier),
                model_version = VALUES(model_version),
                threshold = VALUES(threshold),
                features_json = VALUES(features_json)
        """
        
        for idx, row in df_visits.iterrows():
            row_clean = row.where(pd.notnull(row), None)
            features_payload = {
                'visit_id': row_clean.get('visit_id'),
                'patient_id': row_clean.get('patient_id'),
                'visit_date': str(row_clean.get('visit_date')),
                'service_type': row_clean.get('service_type'),
                'diagnosis_group': row_clean.get('diagnosis_group'),
                'provider_specialty': row_clean.get('provider_specialty'),
                'score': float(scores[idx])
            }
            features_json = json.dumps(features_payload, default=str)
            cursor.execute(insert_sql, (
                row['visit_id'],
                row['patient_id'],
                float(scores[idx]),
                risk_tiers[idx],
                model_version,
                float(self.threshold),
                features_json
            ))
        
        conn.commit()
        cursor.close()
        conn.close()
        
        print(f"Saved {len(df_visits)} predictions to ml_predictions table")
    
    def generate_followups(self, df_visits, scores, risk_tiers):
        """
        Generate follow-up queue entries for high-risk patients.
        
        Args:
            df_visits: Original visit dataframe
            scores: Risk scores
            risk_tiers: Risk tier labels
        """
        if not HAS_PYMYSQL:
            print("Skipping follow-up generation (no pymysql)")
            return
        
        # Apply PWD override for priority, but entries go to queue for high-risk only
        high_risk_mask = risk_tiers == 'High'
        if not high_risk_mask.any():
            print("No high-risk patients to add to follow-up queue")
            return
        
        df_high_risk = df_visits[high_risk_mask].copy()
        
        # Calculate suggested dates (7-14 days out, avoiding weekends)
        def next_business_day(date, days=10):
            current = date
            added = 0
            while added < days:
                current += timedelta(days=1)
                if current.weekday() < 5:  # Monday = 0, Friday = 4
                    added += 1
            return current
        
        df_high_risk['suggested_date'] = df_high_risk['visit_date'].apply(
            lambda x: next_business_day(x, 10)
        )
        
        conn = self._get_db_connection()
        cursor = conn.cursor()
        
        model_version = self.config.get('model_version', 'v0.1.0')
        
        insert_sql = """
            INSERT INTO followup_queue 
            (patient_id, visit_id, priority_level, suggested_date, reason, model_version)
            VALUES (%s, %s, %s, %s, %s, %s)
        """
        
        for _, row in df_high_risk.iterrows():
            reason = f"High-risk patient (score: {scores[row.name]:.3f})"
            priority = 'Priority'
            # PWD override or high score already ensured high_risk; keep Priority for clarity
            if 'pwd_flag' in row and int(row['pwd_flag']) == 1:
                priority = 'Priority'
            
            cursor.execute(insert_sql, (
                row['patient_id'],
                row['visit_id'],
                priority,
                row['suggested_date'].strftime('%Y-%m-%d'),
                reason,
                model_version
            ))
        
        conn.commit()
        cursor.close()
        conn.close()
        
        print(f"Generated {len(df_high_risk)} follow-up queue entries")
    
    def run(self, since_date=None, all_records=False):
        """Main scoring pipeline."""
        print("=" * 60)
        print("ML Batch Scorer - Risk Prediction Pipeline")
        print("=" * 60)
        
        # 1. Fetch visits
        print("\n1. Fetching visits...")
        df_visits = self.fetch_visits(since_date, all_records)
        print(f"   Found {len(df_visits)} visits")
        
        if len(df_visits) == 0:
            print("No visits to score. Exiting.")
            return
        
        # 2. Build features
        print("\n2. Building features...")
        X = self.build_features(df_visits)
        print(f"   Feature matrix shape: {X.shape}")
        
        # 3. Predict
        print("\n3. Generating predictions...")
        scores = self.predict(X)
        risk_tiers = np.array([self.score_to_tier(s) for s in scores])
        
        # Print summary
        high_count = int(np.sum(risk_tiers == 'High'))
        high_pct = (high_count / len(risk_tiers) * 100) if len(risk_tiers) else 0
        print(f"\n   Predictions: {len(scores)}")
        print(f"   High-risk: {high_count} ({high_pct:.1f}%)")
        
        # 4. Save predictions
        print("\n4. Saving predictions...")
        self.save_predictions(df_visits, scores, risk_tiers)
        
        # 5. Generate follow-ups
        print("\n5. Generating follow-up queue...")
        self.generate_followups(df_visits, scores, risk_tiers)
        
        print("\n" + "=" * 60)
        print("Batch scoring complete!")
        print("=" * 60)


def main():
    parser = argparse.ArgumentParser(description='ML Batch Scorer')
    parser.add_argument('--since', type=str, help='Score visits since date (YYYY-MM-DD)')
    parser.add_argument('--all', action='store_true', help='Score all visits from CSV')
    parser.add_argument('--config', type=str, default='config.yaml', help='Config file path')
    
    args = parser.parse_args()
    
    try:
        scorer = BatchScorer(args.config)
        scorer.run(since_date=args.since, all_records=args.all)
    except Exception as e:
        print(f"Error: {e}", file=sys.stderr)
        import traceback
        traceback.print_exc()
        sys.exit(1)


if __name__ == '__main__':
    main()

