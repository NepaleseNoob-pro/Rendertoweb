

import requests
from bs4 import BeautifulSoup
import time
import os
import threading
from flask import Flask, send_from_directory

# Configuration
LOGIN_URL = 'https://gurumantrapsc.com/customer/login'
HOME_URL = 'https://gurumantrapsc.com/home'
PHONE_NUMBER = '9809642422'
PASSWORD = 'rupesh123'
RESULTS_FILE = 'education_latest.txt'

# Flask App setup
app = Flask(__name__)

@app.route('/education_latest.txt')
def serve_education_file():
    return send_from_directory(os.getcwd(), RESULTS_FILE, as_attachment=False)

def get_csrf_token(session, url):
    """Fetches a page and extracts the CSRF token."""
    try:
        response = session.get(url)
        response.raise_for_status()
        soup = BeautifulSoup(response.text, 'html.parser')
        token_tag = soup.find('input', {'name': '_token'})
        if token_tag:
            return token_tag['value']
    except requests.exceptions.RequestException as e:
        print(f"Error fetching CSRF token: {e}")
    return None

def login(session, csrf_token):
    """Logs into the website."""
    payload = {
        '_token': csrf_token,
        'phone_number': PHONE_NUMBER,
        'password': PASSWORD,
    }
    try:
        response = session.post(LOGIN_URL, data=payload)
        response.raise_for_status()
        # Check for successful login, e.g., by checking the URL or page content
        if "customer/dashboard" in response.url:
            print("Login successful.")
            return True
        else:
            print("Login failed. Check credentials or website changes.")
            return False
    except requests.exceptions.RequestException as e:
        print(f"Error during login: {e}")
        return False

def fetch_video_info(session, video_id):
    """Fetches and extracts video information."""
    video_url = f'https://gurumantrapsc.com/purchase/video/{video_id}'
    try:
        headers = {
            'Referer': 'https://gurumantrapsc.com/customer/dashboard'
        }
        response = session.get(video_url, headers=headers)
        response.raise_for_status() # Raise HTTPError for bad responses (4xx or 5xx)

        soup = BeautifulSoup(response.text, 'html.parser')

        # Extract YouTube URL
        youtube_url = None
        iframe = soup.find('iframe')
        if iframe and iframe.has_attr('src') and 'youtube.com/embed/' in iframe['src']:
            # Split the src URL by /embed/ and take the second part, then split by ? to remove params
            video_id_youtube = iframe['src'].split('/embed/')[1].split('?')[0]
            youtube_url = f"https://www.youtube.com/watch?v={video_id_youtube}"

        # Extract Title
        title = None
        header = soup.find('div', class_='dashboard-header')
        if header and header.find('p'):
            title = header.find('p').text.strip()
        elif soup.title:
            title = soup.title.string.replace("| Gurumantra", "").strip()

        return youtube_url, title

    except requests.exceptions.RequestException as e:
        print(f"Error fetching video {video_id}: {e}")
        return None, None

def scrape_videos():
    """Main function to run the scraper."""
    # In the pull model, we start from 0 and let the remote sync handle duplicates
    # The existing_results set is now only for the current run to avoid immediate duplicates
    existing_results = set()

    # Ensure the results file exists
    if not os.path.exists(RESULTS_FILE):
        with open(RESULTS_FILE, 'w') as f:
            pass  # Create the file if it doesn't exist

    with requests.Session() as session:

        session.headers.update({
            'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Accept-Language': 'en-US,en;q=0.9',
            'Accept-Encoding': 'gzip, deflate, br',
            'Connection': 'keep-alive',
            'Upgrade-Insecure-Requests': '1'
        })

        csrf_token = get_csrf_token(session, HOME_URL)
        if not csrf_token:
            print("Could not get CSRF token. Exiting scraping thread.")
            return

        if not login(session, csrf_token):
            print("Login failed. Exiting scraping thread.")
            return

        current_video_id = 317
        last_found_video_id = 0
        consecutive_not_found = 0

        while True:
            print(f"Fetching video ID: {current_video_id}...")

            # Read existing results from the file to avoid re-adding in this run
            # This is important if the script restarts or runs for a long time
            try:
                with open(RESULTS_FILE, 'r') as f:
                    for line in f:
                        if '=' in line:
                            existing_results.add(line.split('=')[0])
            except FileNotFoundError:
                pass # File might not exist yet

            if str(current_video_id) in existing_results:
                print(f"SKIPPED (already exists): Video ID: {current_video_id}")
                current_video_id += 1
                continue

            youtube_url, title = fetch_video_info(session, current_video_id)

            if youtube_url and title:
                result_line = f"{current_video_id}={youtube_url}:{title}\n"
                print(f"FOUND: {result_line}", end='')

                with open(RESULTS_FILE, 'a') as f:
                    f.write(result_line)

                last_found_video_id = current_video_id
                consecutive_not_found = 0
            else:
                consecutive_not_found += 1
                if consecutive_not_found >= 100:
                    print(f"INFO: 100 consecutive videos not found. Restarting from last found ID: {last_found_video_id}")
                    current_video_id = last_found_video_id
                    consecutive_not_found = 0

            print("------------------------------------")
            time.sleep(1)
            current_video_id += 1

if __name__ == "__main__":
    # Start the scraping in a separate thread
    scraper_thread = threading.Thread(target=scrape_videos)
    scraper_thread.daemon = True # Allow the main program to exit even if the thread is running
    scraper_thread.start()

    # Start the Flask web server
    # Render automatically sets the PORT environment variable
    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port)

