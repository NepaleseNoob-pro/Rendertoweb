import requests
from bs4 import BeautifulSoup
import time
import os
import threading
from flask import Flask, send_from_directory

# Configuration
LOGIN_URL = 'https://gurumantrapsc.com/customer/login'
HOME_URL = 'https://gurumantrapsc.com/home'
PHONE_NUMBER = os.environ.get('GURU_PHONE')     # set in Render dashboard
PASSWORD = os.environ.get('GURU_PASS')          # set in Render dashboard
RESULTS_FILE = 'education_latest.txt'

# Flask App setup
app = Flask(__name__)

@app.route('/')
@app.route('/ping')
def ping():
    return 'PONG', 200

@app.route('/education_latest.txt')
def serve_education_file():
    return send_from_directory(os.getcwd(), RESULTS_FILE, as_attachment=False)

def get_csrf_token(session, url):
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
    payload = {
        '_token': csrf_token,
        'phone_number': PHONE_NUMBER,
        'password': PASSWORD,
    }
    try:
        response = session.post(LOGIN_URL, data=payload)
        response.raise_for_status()
        if "customer/dashboard" in response.url:
            print("Login successful.")
            return True
        else:
            print("Login failed.")
            return False
    except requests.exceptions.RequestException as e:
        print(f"Error during login: {e}")
        return False

def fetch_video_info(session, video_id):
    video_url = f'https://gurumantrapsc.com/purchase/video/{video_id}'
    try:
        headers = {
            'Referer': 'https://gurumantrapsc.com/customer/dashboard'
        }
        response = session.get(video_url, headers=headers)
        response.raise_for_status()

        soup = BeautifulSoup(response.text, 'html.parser')

        youtube_url = None
        iframe = soup.find('iframe')
        if iframe and iframe.has_attr('src') and 'youtube.com/embed/' in iframe['src']:
            video_id_youtube = iframe['src'].split('/embed/')[1].split('?')[0]
            youtube_url = f"https://www.youtube.com/watch?v={video_id_youtube}"

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
    existing_results = set()

    if not os.path.exists(RESULTS_FILE):
        with open(RESULTS_FILE, 'w') as f:
            pass

    with requests.Session() as session:
        session.headers.update({
            'User-Agent': 'Mozilla/5.0',
            'Accept': '*/*',
        })

        csrf_token = get_csrf_token(session, HOME_URL)
        if not csrf_token:
            print("Could not get CSRF token.")
            return

        if not login(session, csrf_token):
            print("Login failed.")
            return

        current_video_id = 6344
        last_found_video_id = 0
        consecutive_not_found = 0

        while True:
            try:
                with open(RESULTS_FILE, 'r') as f:
                    for line in f:
                        if '=' in line:
                            existing_results.add(line.split('=')[0])
            except FileNotFoundError:
                pass

            if str(current_video_id) in existing_results:
                print(f"SKIPPED: {current_video_id}")
                current_video_id += 1
                continue

            print(f"Fetching video ID: {current_video_id}")
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
                print(f"Not found: {current_video_id}")
                if consecutive_not_found >= 100:
                    print(f"100 not found. Restarting from {last_found_video_id}")
                    current_video_id = last_found_video_id
                    consecutive_not_found = 0

            print("------------------------------------")
            time.sleep(1)
            current_video_id += 1

def auto_restart_scraper():
    while True:
        try:
            scrape_videos()
        except Exception as e:
            print(f"[AutoRestart] Scraper crashed: {e}")
        print("Restarting in 5 seconds...")
        time.sleep(5)

if __name__ == "__main__":
    scraper_thread = threading.Thread(target=auto_restart_scraper)
    scraper_thread.daemon = True
    scraper_thread.start()

    port = int(os.environ.get('PORT', 5000))
    app.run(host='0.0.0.0', port=port)
