from flask import Flask, render_template, send_from_directory, abort, request, make_response, redirect, url_for, Response
import csv
from datetime import datetime
import os

app = Flask(__name__, template_folder='.')

# In a real application, you would use a more robust method for caching.
schedule_cache = {}
html_to_category_map = {}

def load_schedule():
    """
    Loads the schedule from schedule2025.csv into memory.
    """
    if html_to_category_map: # Already loaded
        return

    with open('schedule2025.csv', 'r') as f:
        reader = csv.DictReader(f)
        for row in reader:
            date = row['date']
            if date not in schedule_cache:
                schedule_cache[date] = []
            schedule_cache[date].append(row)
            html_to_category_map[row['html_name']] = row['category']

def get_scheduled_files(date):
    """
    Returns a list of files scheduled for the given date.
    """
    return schedule_cache.get(date, [])

@app.route('/')
def index():
    return render_template('index.html')

@app.route('/imagenes/<path:filename>')
def serve_image(filename):
    today = '2025-08-12' # Using a date that has entries in the CSV for testing
    scheduled_files = get_scheduled_files(today)

    html_filename = os.path.splitext(filename)[0] + '.html'

    is_scheduled = False
    for f in scheduled_files:
        if f['html_name'] == html_filename:
            is_scheduled = True
            break

    if not is_scheduled:
        abort(404)

    return send_from_directory('imagenes', 'sample.jpg')


@app.route('/<category>/<path:filename>')
def serve_page(category, filename):
    # Today's date in YYYY-MM-DD format
    # In a real scenario, you would use today = datetime.now().strftime('%Y-%m-%d')
    today = '2025-08-12' # Using a date that has entries in the CSV for testing

    scheduled_files = get_scheduled_files(today)

    requested_file_info = None
    for f in scheduled_files:
        if f['html_name'] == filename and f'category{f["category"]}' == category:
            requested_file_info = f
            break

    if not requested_file_info:
        abort(404)

    # Read the template
    with open('pics-lovelyviolet-pagetemplate.html', 'r') as f:
        template_content = f.read()

    image_name = os.path.splitext(filename)[0] + '.jpg'
    image_path_in_html = f'/imagenes/{image_name}'

    page_content = template_content.replace('src=""', f'src="{image_path_in_html}"')

    # Age verification for category2
    if category == 'category2' and request.cookies.get('age_verified') != 'true':
        age_verification_html = """
        <style>
            #age-verification-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0,0,0,0.8);
                z-index: 1000;
                display: flex;
                justify-content: center;
                align-items: center;
            }
            #age-verification-modal {
                background-color: #1f2937;
                padding: 40px;
                border-radius: 10px;
                text-align: center;
                color: white;
            }
            #age-verification-buttons button {
                padding: 10px 20px;
                border: none;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                margin: 0 10px;
            }
            #age-verification-yes {
                background-color: #EB2952;
                color: white;
            }
            #age-verification-no {
                background-color: white;
                color: black;
            }
        </style>
        <div id="age-verification-overlay">
            <div id="age-verification-modal">
                <h2>Age Verification</h2>
                <p>You must be 18 or older to view this content.</p>
                <div id="age-verification-buttons">
                    <button id="age-verification-yes">Yes, I'm over 18</button>
                    <button id="age-verification-no">I'm under 18</button>
                </div>
            </div>
        </div>
        <script>
            document.getElementById('age-verification-yes').addEventListener('click', function() {
                document.cookie = "age_verified=true; path=/; max-age=31536000";
                document.getElementById('age-verification-overlay').style.display = 'none';
            });
            document.getElementById('age-verification-no').addEventListener('click', function() {
                window.location.href = "/";
            });
        </script>
        """
        page_content = page_content.replace('</body>', age_verification_html + '</body>')


    resp = make_response(page_content)
    # This is to ensure the browser doesn't cache the page, so the age verification check happens every time.
    resp.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
    resp.headers['Pragma'] = 'no-cache'
    resp.headers['Expires'] = '0'

    return resp

if __name__ == '__main__':
    load_schedule()
    app.run(host='0.0.0.0', port=5000)
