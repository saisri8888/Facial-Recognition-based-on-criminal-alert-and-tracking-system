from flask import Flask, request, jsonify, make_response
from flask_cors import CORS, cross_origin
from face_engine import FaceEngine
import mysql.connector
import os
import json
import traceback
import sys
import time
from functools import wraps
from dotenv import load_dotenv

# Load environment variables
load_dotenv()

app = Flask(__name__)

# Configure CORS for all routes
app.config['CORS_HEADERS'] = 'Content-Type'
CORS(app, origins="*", methods=["GET", "POST", "OPTIONS"], 
     allow_headers=["Content-Type", "X-API-Key"])

# Track startup time
startup_start = time.time()

# Initialize face engine
print("\n" + "="*60)
print("[STARTUP] Criminal Detection Python API")
print("="*60)
print("[INFO] Initializing Face Engine (encodings only, TensorFlow lazy loads)...")
engine = FaceEngine()
startup_time = time.time() - startup_start
print("[STARTUP] Face Engine initialized in {:.2f}s".format(startup_time))

# Database Configuration from environment
DB_CONFIG = {
    'host': os.getenv('DB_HOST', 'localhost'),
    'user': os.getenv('DB_USER', 'root'),
    'password': os.getenv('DB_PASSWORD', ''),
    'database': os.getenv('DB_NAME', 'criminal_detection_db')
}

# API Configuration from environment
PYTHON_API_KEY = os.getenv('PYTHON_API_KEY', 'default-key-change-this')

def get_db():
    try:
        return mysql.connector.connect(**DB_CONFIG)
    except Exception as e:
        print(f"[ERROR] DB connection failed: {e}")
        return None


def require_api_key(f):
    """Decorator to require API key authentication - AFTER CORS"""
    @wraps(f)
    def decorated_function(*args, **kwargs):
        # Check API key
        api_key = request.headers.get('X-API-Key')
        if not api_key or api_key != PYTHON_API_KEY:
            return jsonify({'success': False, 'error': 'Invalid or missing API key'}), 401
        return f(*args, **kwargs)
    return decorated_function


@app.route('/', methods=['GET'])
def home():
    return jsonify({
        'service': 'Criminal Detection Python API',
        'status': 'running',
        'startup_time_seconds': time.time() - startup_start
    })


@app.route('/api/status', methods=['GET'])
def status():
    try:
        s = engine.get_status()
        s['service'] = 'running'
        s['startup_time_seconds'] = time.time() - startup_start
        s['tensorflow_initialized'] = engine.model_initialized
        return jsonify(s)
    except Exception as e:
        return jsonify({
            'service': 'running', 
            'model_loaded': False, 
            'total_encodings': 0, 
            'total_criminals': 0, 
            'tensorflow_initialized': engine.model_initialized,
            'error': str(e)
        })


@app.route('/api/train', methods=['POST', 'OPTIONS'])
@cross_origin(origins="*", allow_headers=["Content-Type", "X-API-Key"])
@require_api_key
def train_model():
    try:
        data = request.get_json()
        photos = data.get('photos', [])
        if not photos:
            return jsonify({'success': False, 'error': 'No photos provided'})
        print(f"\n[TRAINING] Starting with {len(photos)} photos...")
        result = engine.train(photos)
        return jsonify(result)
    except Exception as e:
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/encode_face', methods=['POST', 'OPTIONS'])
@cross_origin(origins="*", allow_headers=["Content-Type", "X-API-Key"])
@require_api_key
def encode_face():
    try:
        criminal_id = request.form.get('criminal_id')
        photo_id = request.form.get('photo_id')
        temp_path = None

        if 'image' in request.files:
            image_file = request.files['image']
            temp_path = f"temp/temp_{photo_id}.jpg"
            image_file.save(temp_path)
        elif 'image' in request.form:
            temp_path = request.form.get('image')
            if temp_path.startswith('@'):
                temp_path = temp_path[1:]

        if not temp_path or not os.path.exists(temp_path):
            return jsonify({'success': False, 'error': f'Image not found: {temp_path}'})

        db = get_db()
        if not db:
            return jsonify({'success': False, 'error': 'Database connection failed'})
        cursor = db.cursor(dictionary=True)
        cursor.execute("SELECT criminal_code, CONCAT(first_name, ' ', last_name) as name FROM criminals WHERE id = %s", (criminal_id,))
        criminal = cursor.fetchone()
        cursor.close()
        db.close()

        if not criminal:
            return jsonify({'success': False, 'error': 'Criminal not found'})

        result = engine.add_encoding(int(criminal_id), int(photo_id), temp_path, criminal['criminal_code'], criminal['name'])

        cleanup = f"temp/temp_{photo_id}.jpg"
        if os.path.exists(cleanup):
            os.remove(cleanup)

        return jsonify(result)
    except Exception as e:
        traceback.print_exc()
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/detect', methods=['POST', 'OPTIONS'])
@cross_origin(origins="*", allow_headers=["Content-Type", "X-API-Key"])
@require_api_key
def detect():
    db = None
    try:
        data = request.get_json()
        if not data:
            error_response = make_response(json.dumps({'success': False, 'error': 'No JSON data received'}), 400)
            error_response.headers['Content-Type'] = 'application/json'
            return error_response

        frame = data.get('frame', '')
        threshold = float(data.get('threshold', 55))

        if not frame:
            error_response = make_response(json.dumps({'success': False, 'error': 'No frame data'}), 400)
            error_response.headers['Content-Type'] = 'application/json'
            return error_response

        if len(frame) < 100:
            error_response = make_response(json.dumps({'success': False, 'error': 'Frame data too small'}), 400)
            error_response.headers['Content-Type'] = 'application/json'
            return error_response

        result = engine.detect_and_recognize(frame, threshold)

        if not result.get('success'):
            error_response = make_response(json.dumps(result), 500)
            error_response.headers['Content-Type'] = 'application/json'
            return error_response

        # Enrich matches with DB data
        if result.get('matches'):
            db = get_db()
            if db:
                try:
                    cursor = db.cursor(dictionary=True)
                    for match in result['matches']:
                        try:
                            cursor.execute("""
                                SELECT c.crime_type, c.danger_level, c.status, cp.photo_path
                                FROM criminals c
                                LEFT JOIN criminal_photos cp ON c.id = cp.criminal_id AND cp.is_primary = 1
                                WHERE c.id = %s
                            """, (match['criminal_id'],))
                            cdata = cursor.fetchone()
                            if cdata:
                                match['crime_type'] = cdata['crime_type']
                                match['danger_level'] = cdata['danger_level']
                                match['status'] = cdata['status']
                                if cdata['photo_path']:
                                    match['photo'] = f"http://localhost/criminal/{cdata['photo_path']}"
                        except Exception as e:
                            print(f"[WARN] DB enrich error for match {match.get('criminal_id')}: {e}")
                    cursor.close()
                except Exception as e:
                    print(f"[WARN] DB cursor error: {e}")

        # Log detection
        faces = result.get('faces_detected', 0)
        matches = len(result.get('matches', []))
        if faces > 0 or matches > 0:
            print(f"[DETECT] Faces: {faces}, Matches: {matches}")

        # Build response explicitly
        response_data = json.dumps(result)
        response = make_response(response_data, 200)
        response.headers['Content-Type'] = 'application/json'
        response.headers['Content-Length'] = len(response_data)
        response.headers['Cache-Control'] = 'no-cache, no-store, must-revalidate'
        print(f"[DEBUG] Detect response: {len(response_data)} bytes, has {faces} faces, {matches} matches")
        return response

    except ValueError as e:
        print(f"[ERROR] Value error in detect: {e}")
        error_response = make_response(json.dumps({'success': False, 'error': f'Invalid parameter: {str(e)}'}), 400)
        error_response.headers['Content-Type'] = 'application/json'
        return error_response
    except Exception as e:
        import traceback
        print(f"[ERROR] Detect endpoint exception: {e}")
        traceback.print_exc()
        error_response = make_response(json.dumps({'success': False, 'error': str(e)}), 500)
        error_response.headers['Content-Type'] = 'application/json'
        return error_response
    finally:
        if db:
            try:
                db.close()
            except Exception as e:
                print(f"[WARN] DB close error: {e}")


@app.route('/api/remove_criminal', methods=['POST', 'OPTIONS'])
@cross_origin(origins="*", allow_headers=["Content-Type", "X-API-Key"])
@require_api_key
def remove_criminal():
    try:
        data = request.get_json()
        criminal_id = data.get('criminal_id')
        result = engine.remove_criminal(int(criminal_id))
        return jsonify(result)
    except Exception as e:
        return jsonify({'success': False, 'error': str(e)})


@app.route('/api/test', methods=['GET'])
def test():
    return jsonify({
        'status': 'ok',
        'python_version': sys.version,
        'model': engine.get_status()
    })


if __name__ == '__main__':
    print("=" * 70)
    print("  [OK] CRIMINAL DETECTION - Python Face Recognition Service")
    print("=" * 70)
    print("  Model Status      : {}".format('LOADED' if engine.is_loaded else 'NOT LOADED'))
    print("  Encodings         : {}".format(len(engine.known_encodings)))
    print("  TensorFlow        : {}".format('READY' if engine.model_initialized else 'LAZY-LOADED ON FIRST USE'))
    print("  API Running on    : http://0.0.0.0:5001")
    print("  API Startup Time  : {:.2f} seconds".format(time.time() - startup_start))
    print("=" * 70)
    print("  NOTE: First detection will take 5-10s as TensorFlow initializes")
    print("  NOTE: Subsequent detections will be fast (<1s per frame)")
    print("=" * 70)

    app.run(host='0.0.0.0', port=5001, debug=False, threaded=True)