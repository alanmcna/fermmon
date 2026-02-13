-- Outlier analysis: find readings where CO2 or tVOC exceed typical fermentation range
-- Typical: 1–3k ppm CO2, 500–2k ppb tVOC. Spikes to 16k+ are often sensor glitches.

-- Count outliers per version
SELECT version, COUNT(*) AS total,
       SUM(CASE WHEN co2 > 6000 OR tvoc > 6000 THEN 1 ELSE 0 END) AS outliers
FROM readings
GROUP BY version;

-- Mark outliers (optional: add is_outlier column to schema first)
-- ALTER TABLE readings ADD COLUMN is_outlier INTEGER DEFAULT 0;
-- UPDATE readings SET is_outlier = 1 WHERE co2 > 6000 OR tvoc > 6000;
