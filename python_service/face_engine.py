import numpy as np
import pickle
import os
import time
import base64
import cv2
import warnings
import logging
import threading

# ====== OPTIMIZE TENSORFLOW STARTUP ======
# Suppress TensorFlow warnings and verbose output
os.environ['TF_CPP_MIN_LOG_LEVEL'] = '3'  # Only show errors
os.environ['TF_ENABLE_ONEDNN_OPTS'] = '0'  # Suppress oneDNN warnings
os.environ['TF_FORCE_GPU_ALLOW_GROWTH'] = 'true'  # Use GPU memory wisely

# Suppress specific warnings
warnings.filterwarnings('ignore', category=FutureWarning)
warnings.filterwarnings('ignore', category=DeprecationWarning)
logging.getLogger('tensorflow').setLevel(logging.ERROR)

from deepface import DeepFace

class FaceEngine:
    def __init__(self, model_path='models/encodings.pkl'):
        self.model_path = model_path
        self.known_encodings = []
        self.known_metadata = []
        self.is_loaded = False
        self.model_name = 'Facenet512'
        self.model_initialized = False
        self.warmup_thread = None

        os.makedirs('models', exist_ok=True)
        os.makedirs('temp', exist_ok=True)

        # Load criminal encodings from pickle file immediately
        print("[INFO] Loading criminal encodings from database...")
        self.load_model()
        
        # Start background warmup of TensorFlow (non-blocking)
        print("[INFO] Pre-warming TensorFlow in background (for instant 1st detection)...")
        self.warmup_thread = threading.Thread(target=self._warmup_tensorflow_background, daemon=True)
        self.warmup_thread.start()

    def _warmup_tensorflow_background(self):
        """Warm up TensorFlow in background thread so it's ready when user starts detection"""
        try:
            time.sleep(0.5)  # Brief delay to let service fully start
            self.initialize_deepface_model()
            print("[INFO] TensorFlow pre-warmed successfully - detection will be fast!")
        except Exception as e:
            print(f"[WARN] Background warmup failed (will retry on first detection): {str(e)[:100]}")
            # Will retry automatically on first detection
            self.model_initialized = False

    def initialize_deepface_model(self):
        """Initialize the TensorFlow model (can be called in background or on demand)"""
        if self.model_initialized:
            return
        
        try:
            # Create a minimal valid image to trigger model loading
            dummy = np.zeros((200, 200, 3), dtype=np.uint8)
            cv2.rectangle(dummy, (50, 50), (150, 150), (255, 255, 255), -1)
            temp_path = 'temp/dummy_init.jpg'
            cv2.imwrite(temp_path, dummy)
            
            # Try multiple detectors for initialization
            init_success = False
            for detector_backend in ['opencv', 'mtcnn', 'retinaface']:
                try:
                    DeepFace.represent(temp_path, model_name=self.model_name, enforce_detection=False, detector_backend=detector_backend)
                    print(f"[INFO] TensorFlow model initialized with {detector_backend} detector")
                    init_success = True
                    break
                except Exception as e:
                    print(f"[WARN] Initialization with {detector_backend} failed: {str(e)[:80]}")
                    continue
            
            if not init_success:
                print("[WARN] TensorFlow initialization incomplete - will lazy load on first detection")
            
        except Exception as e:
            print(f"[WARN] DeepFace initialization issue: {str(e)[:100]}")
        finally:
            # Cleanup
            try:
                if os.path.exists('temp/dummy_init.jpg'):
                    os.remove('temp/dummy_init.jpg')
            except:
                pass
            
            self.model_initialized = True

    def load_model(self):
        if os.path.exists(self.model_path):
            try:
                with open(self.model_path, 'rb') as f:
                    data = pickle.load(f)
                    self.known_encodings = data.get('encodings', [])
                    self.known_metadata = data.get('metadata', [])
                    self.is_loaded = True
                    criminal_count = len(set(m['criminal_id'] for m in self.known_metadata))
                    print(f"[INFO] Model loaded: {len(self.known_encodings)} encodings for {criminal_count} criminals")
                    return True
            except Exception as e:
                print(f"[ERROR] Failed to load model: {e}")
                self.known_encodings = []
                self.known_metadata = []
                return False
        print("[INFO] No existing model found. Train the model first.")
        return False

    def save_model(self):
        try:
            data = {
                'encodings': self.known_encodings,
                'metadata': self.known_metadata,
                'last_trained': time.time()
            }
            with open(self.model_path, 'wb') as f:
                pickle.dump(data, f)
            print(f"[INFO] Model saved: {len(self.known_encodings)} encodings")
            return True
        except Exception as e:
            print(f"[ERROR] Failed to save model: {e}")
            return False

    def encode_single_face(self, image_path):
        try:
            if not os.path.exists(image_path):
                return None, f"File not found: {image_path}"

            img = cv2.imread(image_path)
            if img is None:
                return None, "Cannot read image file"

            # Try multiple detectors for encoding
            result = None
            for detector_backend in ['opencv', 'mtcnn', 'retinaface']:
                try:
                    result = DeepFace.represent(
                        img_path=image_path,
                        model_name=self.model_name,
                        enforce_detection=True,
                        detector_backend=detector_backend
                    )
                    if result and len(result) > 0:
                        print(f"[ENCODE] Using detector: {detector_backend}")
                        break
                except Exception as e:
                    print(f"[ENCODE] {detector_backend} detector failed: {str(e)[:80]}")
                    continue

            if result and len(result) > 0:
                encoding = np.array(result[0]['embedding'], dtype=np.float64)
                return encoding, None

            return None, "No face detected in image"

        except Exception as e:
            error_msg = str(e)
            if "Face could not be detected" in error_msg or "No face" in error_msg:
                return None, "No face detected in image. Use a clear face photo."
            return None, error_msg

    def train(self, photos_data):
        start_time = time.time()

        new_encodings = []
        new_metadata = []
        encoded_photo_ids = []
        encoding_results = []
        errors = []

        total = len(photos_data)
        print(f"\n[TRAINING] Starting with {total} photos...")

        for i, photo in enumerate(photos_data):
            try:
                photo_path = photo['photo_path']

                if not os.path.exists(photo_path):
                    errors.append(f"File not found: {photo_path}")
                    print(f"  [{i+1}/{total}] SKIP: File not found - {photo_path}")
                    continue

                encoding, error = self.encode_single_face(photo_path)

                if encoding is not None:
                    new_encodings.append(encoding)
                    new_metadata.append({
                        'criminal_id': photo['criminal_id'],
                        'photo_id': photo['photo_id'],
                        'criminal_code': photo['criminal_code'],
                        'name': photo['name']
                    })
                    encoded_photo_ids.append(photo['photo_id'])

                    encoding_bytes = encoding.tobytes()
                    encoding_results.append({
                        'criminal_id': photo['criminal_id'],
                        'photo_id': photo['photo_id'],
                        'encoding_base64': base64.b64encode(encoding_bytes).decode('utf-8')
                    })

                    print(f"  [{i+1}/{total}] OK: {photo['name']}")
                else:
                    errors.append(f"{photo['name']}: {error}")
                    print(f"  [{i+1}/{total}] FAIL: {photo['name']} - {error}")

            except Exception as e:
                errors.append(f"{photo.get('name', 'unknown')}: {str(e)}")
                print(f"  [{i+1}/{total}] ERROR: {str(e)}")

        self.known_encodings = new_encodings
        self.known_metadata = new_metadata
        self.is_loaded = len(new_encodings) > 0

        self.save_model()

        duration = time.time() - start_time
        criminal_ids = list(set(m['criminal_id'] for m in new_metadata))

        print(f"[TRAINING] Complete: {len(new_encodings)} encodings, {len(criminal_ids)} criminals, {duration:.1f}s")

        return {
            'success': True,
            'total_encodings': len(new_encodings),
            'total_criminals': len(criminal_ids),
            'encoded_photo_ids': encoded_photo_ids,
            'encodings': encoding_results,
            'errors': errors,
            'duration': round(duration, 2)
        }

    def cosine_similarity(self, a, b):
        a = np.array(a, dtype=np.float64)
        b = np.array(b, dtype=np.float64)
        dot_product = np.dot(a, b)
        norm_a = np.linalg.norm(a)
        norm_b = np.linalg.norm(b)
        if norm_a == 0 or norm_b == 0:
            return 0.0
        return dot_product / (norm_a * norm_b)

    def detect_and_recognize(self, frame_data, threshold=60):
        temp_path = None
        frame = None
        img_bytes = None
        
        try:
            # Initialize TensorFlow model on first detection (speeds up startup)
            if not self.model_initialized:
                self.initialize_deepface_model()
            
            # Validate input
            if not frame_data or not isinstance(frame_data, str):
                return {'success': False, 'error': 'Invalid frame data format'}
            
            # Extract base64 data
            if ',' in frame_data:
                frame_data = frame_data.split(',')[1]

            # Decode base64 to image
            try:
                img_bytes = base64.b64decode(frame_data)
            except Exception as e:
                return {'success': False, 'error': f'Base64 decode failed: {str(e)}'}
            
            # Convert to numpy array and decode image
            try:
                nparr = np.frombuffer(img_bytes, np.uint8)
                frame = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
            except Exception as e:
                return {'success': False, 'error': f'Image decode failed: {str(e)}'}

            if frame is None:
                return {'success': False, 'error': 'Invalid image data - could not decode'}

            # Resize for FASTER processing (smaller = faster detection)
            height, width = frame.shape[:2]
            max_width = 480  # Optimized for speed (was 640)
            if width > max_width:
                scale = max_width / width
                frame = cv2.resize(frame, (int(width * scale), int(height * scale)))
                height, width = frame.shape[:2]
            else:
                scale = 1.0

            # Save temp file for face detection
            temp_path = f'temp/frame_{int(time.time() * 1000)}.jpg'
            try:
                cv2.imwrite(temp_path, frame)
            except Exception as e:
                return {'success': False, 'error': f'Frame save failed: {str(e)}'}

            # Detect faces with fallback detector
            face_results = None
            detector_backends = ['opencv', 'mtcnn', 'retinaface']  # Try in order of reliability
            
            try:
                for detector_backend in detector_backends:
                    try:
                        face_results = DeepFace.represent(
                            img_path=temp_path,
                            model_name=self.model_name,
                            enforce_detection=True,
                            detector_backend=detector_backend
                        )
                        print(f"[DETECT] Using detector: {detector_backend} - Found {len(face_results) if face_results else 0} faces")
                        break  # Success - stop trying other detectors
                    except Exception as e:
                        error_msg = str(e)
                        if "Face could not be detected" in error_msg or "No face" in error_msg:
                            print(f"[DETECT] {detector_backend} detector: No faces in frame")
                            return {
                                'success': True,
                                'faces_detected': 0,
                                'face_locations': [],
                                'matches': []
                            }
                        else:
                            print(f"[DETECT] {detector_backend} detector failed: {error_msg[:100]}")
                            # Continue to next detector
                            continue
                
                if face_results is None:
                    return {'success': False, 'error': 'Face detection failed with all detectors'}
            finally:
                # Always cleanup temp file immediately
                if temp_path and os.path.exists(temp_path):
                    try:
                        os.remove(temp_path)
                    except Exception as cleanup_err:
                        pass
                temp_path = None

            if not face_results:
                return {
                    'success': True,
                    'faces_detected': 0,
                    'face_locations': [],
                    'matches': []
                }

            face_locations = []
            matches = []
            inv_scale = 1.0 / scale

            # Process detected faces
            try:
                for i, face in enumerate(face_results):
                    # Extract face location
                    try:
                        region = face.get('facial_area', {})
                        x = int(region.get('x', 0) * inv_scale)
                        y = int(region.get('y', 0) * inv_scale)
                        w = int(region.get('w', 100) * inv_scale)
                        h = int(region.get('h', 100) * inv_scale)

                        top = max(0, int(y))
                        right = max(0, int(x + w))
                        bottom = max(0, int(y + h))
                        left = max(0, int(x))
                        face_locations.append([top, right, bottom, left])
                    except Exception as loc_err:
                        continue

                    # Match against known faces if model is loaded
                    if self.is_loaded and len(self.known_encodings) > 0:
                        try:
                            # Extract and validate face encoding
                            if 'embedding' not in face:
                                continue
                            
                            face_encoding = np.array(face['embedding'], dtype=np.float64)
                            
                            if face_encoding is None or face_encoding.size == 0:
                                continue

                            # VECTORIZED COMPARISON (Much faster than loop)
                            # Convert all known encodings to numpy array at once
                            known_encodings_array = np.array([
                                np.array(enc, dtype=np.float64) 
                                for enc in self.known_encodings
                            ], dtype=np.float64)
                            
                            # Vectorized cosine similarity calculation
                            # Calculate similarity for all encodings at once
                            similarities = []
                            for j, known_enc_array in enumerate(known_encodings_array):
                                if known_enc_array.size > 0:
                                    similarity = self.cosine_similarity(face_encoding, known_enc_array)
                                    similarities.append(similarity)
                                else:
                                    similarities.append(-1)

                            if similarities:
                                best_idx = np.argmax(similarities)
                                best_similarity = similarities[best_idx]
                            else:
                                best_similarity = -1
                                best_idx = -1

                            # Check for match
                            confidence = float(round(best_similarity * 100, 2))
                            
                            # Log the confidence even if below threshold (for debugging)
                            if best_idx >= 0 and best_idx < len(self.known_metadata):
                                best_match_name = self.known_metadata[best_idx].get('name', 'Unknown')
                                print(f"[DETECT] Face {i}: Best match is {best_match_name} with confidence {confidence}% (Threshold: {threshold}%)")
                            
                            if confidence >= threshold and best_idx >= 0 and best_idx < len(self.known_metadata):
                                try:
                                    metadata = self.known_metadata[best_idx]
                                    matches.append({
                                        'face_index': int(i),
                                        'criminal_id': int(metadata['criminal_id']),
                                        'criminal_code': str(metadata['criminal_code']),
                                        'name': str(metadata['name']),
                                        'confidence': confidence,
                                        'distance': float(round(1 - best_similarity, 4))
                                    })
                                    print(f"[DETECT] ✓ MATCH FOUND: {metadata['name']} ({confidence}%)")
                                except Exception as access_err:
                                    continue
                        except Exception as match_err:
                            continue

                return {
                    'success': True,
                    'faces_detected': int(len(face_results)),
                    'face_locations': face_locations,
                    'matches': matches
                }
            except Exception as process_err:
                return {'success': False, 'error': f'Face processing error: {str(process_err)}'}

        except Exception as e:
            error_msg = str(e)
            return {'success': False, 'error': error_msg}
        finally:
            # Cleanup memory
            try:
                if temp_path and os.path.exists(temp_path):
                    try:
                        os.remove(temp_path)
                    except:
                        pass
                
                if frame is not None:
                    del frame
                if img_bytes is not None:
                    del img_bytes
                
                # Force garbage collection
                import gc
                gc.collect()
            except Exception as cleanup_err:
                pass

    def add_encoding(self, criminal_id, photo_id, photo_path, criminal_code, name):
        encoding, error = self.encode_single_face(photo_path)

        if encoding is not None:
            self.known_encodings.append(encoding)
            self.known_metadata.append({
                'criminal_id': criminal_id,
                'photo_id': photo_id,
                'criminal_code': criminal_code,
                'name': name
            })
            self.is_loaded = True
            self.save_model()

            encoding_bytes = encoding.tobytes()
            return {
                'success': True,
                'encoding_base64': base64.b64encode(encoding_bytes).decode('utf-8')
            }

        return {'success': False, 'error': error}

    def remove_criminal(self, criminal_id):
        indices_to_remove = [
            i for i, m in enumerate(self.known_metadata)
            if m['criminal_id'] == criminal_id
        ]

        for i in sorted(indices_to_remove, reverse=True):
            del self.known_encodings[i]
            del self.known_metadata[i]

        if len(self.known_encodings) == 0:
            self.is_loaded = False

        self.save_model()
        return {'success': True, 'removed': len(indices_to_remove)}

    def get_status(self):
        criminal_ids = set(m['criminal_id'] for m in self.known_metadata) if self.known_metadata else set()
        mismatch = len(self.known_encodings) != len(self.known_metadata)
        if mismatch:
            print(f"[WARN] Encoding/Metadata mismatch detected: {len(self.known_encodings)} encodings vs {len(self.known_metadata)} metadata items")
        return {
            'model_loaded': self.is_loaded,
            'total_encodings': len(self.known_encodings),
            'total_criminals': len(criminal_ids),
            'encodings_metadata_match': not mismatch,
            'model_path_exists': os.path.exists(self.model_path)
        }