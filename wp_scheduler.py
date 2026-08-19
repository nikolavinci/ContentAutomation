#!/usr/bin/env python3
"""
WordPress Content Automation Scheduler
Uses APScheduler to run content generation on a schedule.

Usage:
    python wp_scheduler.py --config sites.yaml
    
This runs as a daemon and publishes articles according to schedule.
"""

import os
import json
import logging
import argparse
import yaml
from datetime import datetime, timedelta
from apscheduler.schedulers.background import BackgroundScheduler
from apscheduler.triggers.cron import CronTrigger
from apscheduler.stores.sqlalchemy import SQLAlchemyJobStore
import atexit

from wp_content_engine import ContentAutomationEngine, ContentConfig

logging.basicConfig(
    level=logging.INFO,
    format='%(asctime)s - %(name)s - %(levelname)s - %(message)s',
    handlers=[
        logging.FileHandler('wp_scheduler.log'),
        logging.StreamHandler()
    ]
)
logger = logging.getLogger(__name__)


class ContentScheduler:
    """Manages scheduled content publication"""
    
    def __init__(self, config_file: str):
        self.config_file = config_file
        self.scheduler = BackgroundScheduler(
            jobstores={
                'default': SQLAlchemyJobStore(url='sqlite:///content_jobs.db')
            }
        )
        self.scheduler.start()
        logger.info("Scheduler initialized")
        
        # Graceful shutdown
        atexit.register(self.shutdown)
    
    def load_sites(self) -> dict:
        """Load site configurations from YAML"""
        with open(self.config_file, 'r') as f:
            return yaml.safe_load(f)
    
    def schedule_sites(self, sites_config: dict):
        """Schedule publishing for all sites"""
        
        for site_name, site_config in sites_config.items():
            config = ContentConfig(
                site_url=site_config["site_url"],
                rest_api_url=site_config["rest_api_url"],
                username=site_config["username"],
                password=site_config["password"],
                category_id=site_config.get("category_id", 1),
                tags=site_config.get("tags", ["business", "innovation"]),
                niche=site_config["niche"],
                authors=site_config.get("authors", ["Editorial Team"])
            )
            
            # Parse schedule (e.g., "08:00,16:00" for 8am and 4pm)
            publish_times = site_config.get("publish_times", ["08:00"])
            
            for time_str in publish_times:
                hour, minute = map(int, time_str.split(':'))
                
                job_id = f"{site_name}_publish_{hour:02d}_{minute:02d}"
                
                # Check if job already exists
                existing_job = self.scheduler.get_job(job_id)
                if existing_job:
                    logger.info(f"Job {job_id} already scheduled, skipping")
                    continue
                
                self.scheduler.add_job(
                    self._publish_task,
                    CronTrigger(hour=hour, minute=minute),
                    args=[config, site_name],
                    id=job_id,
                    name=f"Publish to {site_name} at {time_str}",
                    replace_existing=False
                )
                
                logger.info(f"Scheduled: {site_name} at {time_str}")
    
    def _publish_task(self, config: ContentConfig, site_name: str):
        """Task that runs at scheduled time"""
        logger.info(f"=== Publishing task started for {site_name} ===")
        
        try:
            engine = ContentAutomationEngine(config)
            result = engine.run(
                dry_run=False,  # Actually publish
                draft_only=True  # But as draft for review
            )
            
            # Log result
            self._log_publish_result(site_name, result)
            
            # Send alert if failed
            if not result.get("stages", {}).get("publish", {}).get("success", False):
                self._send_alert(site_name, result)
        
        except Exception as e:
            logger.error(f"Task failed for {site_name}: {e}", exc_info=True)
            self._send_alert(site_name, {"error": str(e)})
    
    def _log_publish_result(self, site_name: str, result: dict):
        """Log publishing result"""
        log_file = f"publish_results_{site_name}.jsonl"
        with open(log_file, 'a') as f:
            f.write(json.dumps({
                "timestamp": datetime.now().isoformat(),
                "site": site_name,
                **result
            }) + '\n')
    
    def _send_alert(self, site_name: str, error_details: dict):
        """Send alert on publish failure (email, Slack, etc.)"""
        logger.warning(f"ALERT: Publish failed for {site_name}")
        # TODO: Implement email or Slack notification
        # For now, just log
    
    def shutdown(self):
        """Graceful shutdown"""
        logger.info("Shutting down scheduler...")
        self.scheduler.shutdown()
    
    def run(self):
        """Start scheduler and keep running"""
        sites = self.load_sites()
        self.schedule_sites(sites)
        
        logger.info("Scheduler running. Press Ctrl+C to stop.")
        try:
            while True:
                import time
                time.sleep(1)
        except KeyboardInterrupt:
            logger.info("Received shutdown signal")


def main():
    parser = argparse.ArgumentParser(description="WordPress Content Scheduler")
    parser.add_argument("--config", default="sites.yaml", help="Sites configuration file")
    parser.add_argument("--list-jobs", action="store_true", help="List all scheduled jobs")
    parser.add_argument("--test-publish", metavar="SITE", help="Test publish for a site")
    
    args = parser.parse_args()
    
    scheduler = ContentScheduler(args.config)
    
    if args.list_jobs:
        # List jobs and exit
        sites = scheduler.load_sites()
        scheduler.schedule_sites(sites)
        for job in scheduler.scheduler.get_jobs():
            print(f"{job.id}: {job.name} - {job.trigger}")
        scheduler.shutdown()
        return
    
    if args.test_publish:
        # Test publish and exit
        sites = scheduler.load_sites()
        if args.test_publish not in sites:
            logger.error(f"Site '{args.test_publish}' not found in config")
            return
        
        site_config = sites[args.test_publish]
        config = ContentConfig(
            site_url=site_config["site_url"],
            rest_api_url=site_config["rest_api_url"],
            username=site_config["username"],
            password=site_config["password"],
            category_id=site_config.get("category_id", 1),
            tags=site_config.get("tags", []),
            niche=site_config["niche"],
            authors=site_config.get("authors", [])
        )
        
        engine = ContentAutomationEngine(config)
        result = engine.run(dry_run=False, draft_only=True)
        print(json.dumps(result, indent=2))
        scheduler.shutdown()
        return
    
    # Normal mode: run scheduler
    scheduler.run()


if __name__ == "__main__":
    main()
