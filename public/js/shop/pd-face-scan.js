/**
 * Guided face scan + PD meter for the shop virtual try-on.
 *
 * Runs 100% in the browser (Google MediaPipe Face Landmarker). No video, image or
 * landmark ever leaves the device. Flow:
 *
 *   front (look at camera) → turn LEFT → turn RIGHT → fuse → measure
 *
 * Every accepted frame's 478 landmarks are normalised (centred, scaled) and rigidly
 * aligned to a frontal reference (Horn's quaternion / Kabsch). The per-landmark median
 * over all views gives a personal 3D face shape that is far less noisy than a single
 * frame, and whose depth (z) is informed by the side views. Metric scale comes from
 * the iris (11.7 ± 0.5 mm, corrected for the mesh's ~5% ring bias) measured on the
 * frontal frames only.
 *
 * Outputs: distance PD (from eye rotation centres → convergence-free), monocular
 * OD/OS, near PD, face width, bridge (inner-canthal) width, face height, ± error.
 *
 * Host contract (frames.blade.php):
 *   const PD = createPdScanner({ cfg, camera, els });
 *   PD.enabled, PD.start(videoEl|null, onLocked, onState) → Promise<bool>, PD.stop(),
 *   PD.renderVerdict(frameJson), PD.hasStored(), PD.willMeasure()
 *   onState('measuring' | 'locked' | 'hidden') → host hides/shows the glasses.
 */

// ---------- landmark indices (MediaPipe 478-point mesh, subject's R/L) ----------
const LM = {
    IRIS_R: 468, IRIS_R_A: 469, IRIS_R_B: 471,
    IRIS_L: 473, IRIS_L_A: 474, IRIS_L_B: 476,
    EYE_R_OUTER: 33, EYE_L_OUTER: 263,
    EYE_R_INNER: 133, EYE_L_INNER: 362,
    NOSE_TIP: 1, NASION: 168,
    FOREHEAD: 10, CHIN: 152,
    CHEEK_R: 234, CHEEK_L: 454,
    MESH_COUNT: 468, TOTAL: 478,
};

const STORE_KEY = 'shop_pd_scan';

// ---------- small math helpers ----------
const d3 = (a, b) => Math.hypot(a[0] - b[0], a[1] - b[1], a[2] - b[2]);
const sub = (a, b) => [a[0] - b[0], a[1] - b[1], a[2] - b[2]];
const add = (a, b) => [a[0] + b[0], a[1] + b[1], a[2] + b[2]];
const mul = (a, k) => [a[0] * k, a[1] * k, a[2] * k];
const dot = (a, b) => a[0] * b[0] + a[1] * b[1] + a[2] * b[2];
const norm = (a) => { const n = Math.hypot(a[0], a[1], a[2]) || 1; return [a[0] / n, a[1] / n, a[2] / n]; };
const clamp = (v, lo, hi) => Math.min(hi, Math.max(lo, v));
const fmt = (v, step = 0.5) => (Math.round(v / step) * step).toFixed(1).replace(/\.0$/, '');

function median(arr) {
    if (!arr.length) return NaN;
    const s = Array.from(arr).sort((a, b) => a - b);
    const m = s.length >> 1;
    return s.length % 2 ? s[m] : (s[m - 1] + s[m]) / 2;
}
function quantile(sorted, q) {
    if (!sorted.length) return NaN;
    const pos = (sorted.length - 1) * q, lo = Math.floor(pos), hi = Math.ceil(pos);
    return sorted[lo] + (sorted[hi] - sorted[lo]) * (pos - lo);
}
function mad(arr) { // robust sigma ≈ 1.4826 * MAD
    const m = median(arr);
    return 1.4826 * median(arr.map((v) => Math.abs(v - m)));
}

/** Horn (1987) absolute orientation: rotation R (3x3 row-major) that maps P onto Q. Both centred, N×3 flat arrays. */
function hornRotation(P, Q, count) {
    let Sxx = 0, Sxy = 0, Sxz = 0, Syx = 0, Syy = 0, Syz = 0, Szx = 0, Szy = 0, Szz = 0;
    for (let i = 0; i < count; i++) {
        const o = i * 3;
        const px = P[o], py = P[o + 1], pz = P[o + 2];
        const qx = Q[o], qy = Q[o + 1], qz = Q[o + 2];
        Sxx += px * qx; Sxy += px * qy; Sxz += px * qz;
        Syx += py * qx; Syy += py * qy; Syz += py * qz;
        Szx += pz * qx; Szy += pz * qy; Szz += pz * qz;
    }
    const N = [
        [Sxx + Syy + Szz, Syz - Szy, Szx - Sxz, Sxy - Syx],
        [Syz - Szy, Sxx - Syy - Szz, Sxy + Syx, Szx + Sxz],
        [Szx - Sxz, Sxy + Syx, -Sxx + Syy - Szz, Syz + Szy],
        [Sxy - Syx, Szx + Sxz, Syz + Szy, -Sxx - Syy + Szz],
    ];
    // Shift so the largest algebraic eigenvalue is also the largest in magnitude, then power-iterate.
    let shift = 0;
    for (let r = 0; r < 4; r++) for (let c = 0; c < 4; c++) shift += Math.abs(N[r][c]);
    for (let r = 0; r < 4; r++) N[r][r] += shift;
    let q = [1, 0.01, 0.01, 0.01];
    for (let it = 0; it < 60; it++) {
        const nq = [0, 0, 0, 0];
        for (let r = 0; r < 4; r++) nq[r] = N[r][0] * q[0] + N[r][1] * q[1] + N[r][2] * q[2] + N[r][3] * q[3];
        const n = Math.hypot(nq[0], nq[1], nq[2], nq[3]) || 1;
        q = nq.map((v) => v / n);
    }
    const [w, x, y, z] = q;
    return [
        w * w + x * x - y * y - z * z, 2 * (x * y - w * z), 2 * (x * z + w * y),
        2 * (x * y + w * z), w * w - x * x + y * y - z * z, 2 * (y * z - w * x),
        2 * (x * z - w * y), 2 * (y * z + w * x), w * w - x * x - y * y + z * z,
    ];
}
function applyRotation(R, P, out) {
    for (let i = 0; i < P.length; i += 3) {
        const x = P[i], y = P[i + 1], z = P[i + 2];
        out[i] = R[0] * x + R[1] * y + R[2] * z;
        out[i + 1] = R[3] * x + R[4] * y + R[5] * z;
        out[i + 2] = R[6] * x + R[7] * y + R[8] * z;
    }
    return out;
}
function rotationAngleDeg(R) {
    const tr = clamp((R[0] + R[4] + R[8] - 1) / 2, -1, 1);
    return Math.acos(tr) * 180 / Math.PI;
}
/** Per-coordinate median over frames. frames: Float32Array[] of equal length. */
function medianShape(frames) {
    const len = frames[0].length;
    const out = new Float32Array(len);
    const col = new Float64Array(frames.length);
    for (let i = 0; i < len; i++) {
        for (let f = 0; f < frames.length; f++) col[f] = frames[f][i];
        out[i] = median(col);
    }
    return out;
}
const pt = (arr, idx) => [arr[idx * 3], arr[idx * 3 + 1], arr[idx * 3 + 2]];

/** Exposed for tests only. */
export const _geom = { hornRotation, applyRotation, rotationAngleDeg, medianShape, median, mad };

// ---------- scanner ----------
export function createPdScanner({ cfg = {}, camera, els, mirrored = true }) {
    const enabled = !!(cfg.enabled && window.isSecureContext && navigator.mediaDevices);
    const IRIS_MM = parseFloat(cfg.iris_diameter_mm) || 11.7;
    const RING_SCALE = parseFloat(cfg.iris_ring_scale) || 0.95;
    const CALIB = parseFloat(cfg.calibration) || 1.0;
    const EYE_ROT_MM = 13.5;          // pupil → eye rotation centre
    const MIN_IRIS_PX = 12;
    const INTERVAL = 100;             // ms between detections
    const ASSUMED_HFOV = 65;          // deg, for distance estimate + rotation-centre geometry
    const NEED_FRONT = 30;            // frontal frames
    const NEED_SIDE = 10;             // frames per side
    const TURN_TARGET = 22;           // deg reached to validate a side
    const TURN_MIN = 10, TURN_MAX = 50;
    const STEP_TIMEOUT = 25000;

    // ---- elements ----
    const E = els;
    const show = (el, on) => { if (el) el.hidden = !on; };
    const text = (el, t) => { if (el) el.textContent = t; };

    // ---- state ----
    let landmarker = null, loadPromise = null;
    let running = false, rafId = null, lastTs = 0, tickCount = 0, lastLum = 128, detectErrors = 0;
    let source = null, ownStream = null, ownVideo = null;
    let onLocked = null, onState = null;
    let visible = true;
    let result = restore();
    let scan = null;                  // active scan session
    let lumCanvas = null, lumCtx = null;

    const emit = (s) => { if (onState) { try { onState(s); } catch (e) { /* noop */ } } };

    function restore() {
        try {
            const raw = sessionStorage.getItem(STORE_KEY);
            if (!raw) return null;
            const v = JSON.parse(raw);
            if (v && isFinite(v.pd) && v.pd > 40 && v.pd < 90) return v;
        } catch (e) { /* noop */ }
        return null;
    }
    function persist(v) { try { sessionStorage.setItem(STORE_KEY, JSON.stringify(v)); } catch (e) { /* noop */ } }
    function forget() { try { sessionStorage.removeItem(STORE_KEY); } catch (e) { /* noop */ } }

    async function load() {
        if (landmarker) return landmarker;
        if (loadPromise) return loadPromise;
        loadPromise = (async () => {
            const vision = await import(cfg.tasks_vision_url);
            const files = await vision.FilesetResolver.forVisionTasks(cfg.wasm_url);
            const make = (delegate) => vision.FaceLandmarker.createFromOptions(files, {
                baseOptions: { modelAssetPath: cfg.model_url, delegate },
                runningMode: 'VIDEO',
                numFaces: 1,
                outputFaceBlendshapes: false,
                outputFacialTransformationMatrixes: false,
            });
            try { landmarker = await make('GPU'); }
            catch (e) { console.warn('PD: GPU delegate failed, using CPU', e); landmarker = await make('CPU'); }
            return landmarker;
        })();
        try { return await loadPromise; } finally { loadPromise = null; }
    }

    function brightness(v) {
        try {
            if (!lumCanvas) {
                lumCanvas = document.createElement('canvas');
                lumCanvas.width = 32; lumCanvas.height = 24;
                lumCtx = lumCanvas.getContext('2d', { willReadFrequently: true });
            }
            lumCtx.drawImage(v, 0, 0, 32, 24);
            const d = lumCtx.getImageData(0, 0, 32, 24).data;
            let sum = 0;
            for (let i = 0; i < d.length; i += 4) sum += 0.299 * d[i] + 0.587 * d[i + 1] + 0.114 * d[i + 2];
            return sum / (d.length / 4);
        } catch (e) { return 128; }
    }

    // ---- per-frame analysis ----
    function analyse(lm, w, h) {
        const P = new Float32Array(LM.TOTAL * 3);
        for (let i = 0; i < LM.TOTAL; i++) {
            P[i * 3] = lm[i].x * w;
            P[i * 3 + 1] = lm[i].y * h;
            P[i * 3 + 2] = lm[i].z * w; // MediaPipe z ≈ same scale as x
        }
        const rC = pt(P, LM.IRIS_R), lC = pt(P, LM.IRIS_L);
        const rW = d3(pt(P, LM.IRIS_R_A), pt(P, LM.IRIS_R_B)) * RING_SCALE;
        const lW = d3(pt(P, LM.IRIS_L_A), pt(P, LM.IRIS_L_B)) * RING_SCALE;
        const irisPx = (rW + lW) / 2;
        const pdPx = d3(rC, lC);

        const nose = pt(P, LM.NOSE_TIP), rCorner = pt(P, LM.EYE_R_OUTER), lCorner = pt(P, LM.EYE_L_OUTER);
        const dR = Math.abs(nose[0] - rCorner[0]), dL = Math.abs(lCorner[0] - nose[0]);
        const asym = (dL - dR) / Math.max(1e-6, dL + dR);
        // yaw > 0 ⇒ subject turned to THEIR left (nose moves toward their left eye corner)
        const yaw = -Math.atan(asym * 1.8) * 180 / Math.PI;
        const roll = Math.atan2(lC[1] - rC[1], lC[0] - rC[0]) * 180 / Math.PI;
        const focalPx = (w / 2) / Math.tan(ASSUMED_HFOV / 2 * Math.PI / 180);
        const distMm = irisPx > 0 ? IRIS_MM * focalPx / irisPx : 9999;
        const cx = (rC[0] + lC[0]) / 2 / w, cy = (rC[1] + lC[1]) / 2 / h;

        // normalise: centre on mesh centroid, unit RMS radius
        let mx = 0, my = 0, mz = 0;
        for (let i = 0; i < LM.MESH_COUNT; i++) { mx += P[i * 3]; my += P[i * 3 + 1]; mz += P[i * 3 + 2]; }
        mx /= LM.MESH_COUNT; my /= LM.MESH_COUNT; mz /= LM.MESH_COUNT;
        let rms = 0;
        for (let i = 0; i < LM.MESH_COUNT; i++) {
            const x = P[i * 3] - mx, y = P[i * 3 + 1] - my, z = P[i * 3 + 2] - mz;
            rms += x * x + y * y + z * z;
        }
        rms = Math.sqrt(rms / LM.MESH_COUNT) || 1;
        const Q = new Float32Array(LM.TOTAL * 3);
        for (let i = 0; i < LM.TOTAL; i++) {
            Q[i * 3] = (P[i * 3] - mx) / rms;
            Q[i * 3 + 1] = (P[i * 3 + 1] - my) / rms;
            Q[i * 3 + 2] = (P[i * 3 + 2] - mz) / rms;
        }

        return {
            shape: Q,
            irisUnits: irisPx / rms,                 // iris width in shape units
            pxPerUnit: rms,
            irisPx, pdPx, yaw, roll, distMm, cx, cy,
            irisSpread: Math.abs(rW - lW) / Math.max(1e-6, irisPx),
            // raw pixel positions for the on-screen iris trackers
            eyeR: [rC[0], rC[1]], eyeL: [lC[0], lC[1]], irisRawPx: irisPx / RING_SCALE,
            // camera position relative to mesh centroid, in shape units (for rotation-centre geometry)
            camUnits: [(w / 2 - mx) / rms, (h / 2 - my) / rms, -distMm * (irisPx / IRIS_MM) / rms],
            nearPdMm: pdPx * IRIS_MM / Math.max(1e-6, irisPx),
        };
    }

    /**
     * Move the two iris rings to where the irises are on screen. The stage shows the video
     * with object-fit: cover and (normally) mirrored, so map video px → stage px accordingly.
     */
    function placeEyes(a, v) {
        if (!E.eyeR || !E.eyeL || !E.stage) return;
        const W = E.stage.clientWidth, H = E.stage.clientHeight;
        const vw = v.videoWidth, vh = v.videoHeight;
        if (!W || !H || !vw || !vh) return;
        const s = Math.max(W / vw, H / vh);
        const ox = (W - vw * s) / 2, oy = (H - vh * s) / 2;
        const size = clamp(a.irisRawPx * s * 1.9, 14, 64); // ring ≈ iris + margin
        const put = (el, p) => {
            let x = ox + p[0] * s;
            if (mirrored) x = W - x;
            const y = oy + p[1] * s;
            el.style.width = `${size}px`; el.style.height = `${size}px`;
            el.style.transform = `translate(-50%, -50%) translate(${x.toFixed(1)}px, ${y.toFixed(1)}px)`;
            el.hidden = false;
        };
        // mirrored view swaps sides visually, but each ring still follows its own iris
        put(E.eyeR, a.eyeR);
        put(E.eyeL, a.eyeL);
    }
    function hideEyes() { show(E.eyeR, false); show(E.eyeL, false); }

    /** Common quality gate; returns a coaching string or null. */
    function gate(a, step) {
        if (a.irisPx < MIN_IRIS_PX || a.distMm > 650) return 'Move a little closer — about 40 cm (16 in) from the camera.';
        if (a.distMm < 250) return 'Move back a little — about 40 cm (16 in) from the camera.';
        if (lastLum < 55) return 'Need more light — face a window or lamp.';
        if (Math.abs(a.roll) > 10) return 'Level your head — keep both eyes on the line.';
        if (step === 'front') {
            if (a.cx < 0.3 || a.cx > 0.7 || a.cy < 0.2 || a.cy > 0.65) return 'Center your face in the oval, eyes on the line.';
            if (Math.abs(a.yaw) > 8) return 'Face the camera straight.';
            if (a.irisSpread > 0.2) return 'Look straight into the camera lens.';
        } else if (a.cx < 0.2 || a.cx > 0.8 || a.cy < 0.15 || a.cy > 0.7) {
            return 'Keep your face inside the oval while turning.';
        }
        return null;
    }

    // ---- scan session ----
    function newScan() {
        return {
            step: 'front',               // front | left | right | compute
            front: [], left: [], right: [],
            maxLeft: 0, maxRight: 0,
            stepStarted: performance.now(),
            skipTurns: false,
        };
    }

    function stepIndex(step) { return { front: 1, left: 2, right: 3 }[step] || 3; }

    function paintStep(progress) {
        if (!scan) return;
        const idx = stepIndex(scan.step);
        text(E.steps, `Step ${idx} of 3 · ${{ front: 'Look at the camera', left: 'Turn left', right: 'Turn right' }[scan.step] || ''}`);
        show(E.arrowLeft, scan.step === 'left');
        show(E.arrowRight, scan.step === 'right');
        show(E.skip, scan.step !== 'front');
        if (E.progress) E.progress.style.width = `${clamp(Math.round(progress * 100), 0, 100)}%`;
    }

    function tick(ts) {
        if (!running) return;
        rafId = requestAnimationFrame(tick);
        if (ts - lastTs < INTERVAL) return;
        lastTs = ts;
        const v = source;
        if (!v || v.readyState < 2 || !v.videoWidth || !scan) return;
        tickCount++;
        if (tickCount % 6 === 0) lastLum = brightness(v);

        let res;
        try { res = landmarker.detectForVideo(v, performance.now()); }
        catch (e) {
            detectErrors++;
            if (detectErrors === 1 || detectErrors % 50 === 0) console.warn('PD detect error', e);
            if (detectErrors > 30) { coach('Face tracking failed on this device — enter your PD below.'); stopLoop(); show(E.actions, true); }
            return;
        }
        detectErrors = 0;
        const lm = res && res.faceLandmarks && res.faceLandmarks[0];
        if (!lm || lm.length < LM.TOTAL) {
            E.guide.classList.remove('is-good');
            hideEyes();
            if (tickCount % 6 !== 0 && tickCount < 6) lastLum = brightness(v);
            coach(lastLum < 25
                ? 'Camera looks dark — uncover the camera or turn on a light.'
                : 'No face found — look at the camera, about 40 cm away.');
            text(E.pillValue, '—');
            return;
        }
        const a = analyse(lm, v.videoWidth, v.videoHeight);
        placeEyes(a, v);
        const elapsed = performance.now() - scan.stepStarted;

        if (scan.step === 'front') {
            const bad = gate(a, 'front');
            if (bad) {
                if (scan.front.length > 6) scan.front.splice(0, 2);
                E.guide.classList.remove('is-good');
                coach(bad);
                paintStep(scan.front.length / NEED_FRONT);
                return;
            }
            E.guide.classList.add('is-good');
            if (a.nearPdMm > 45 && a.nearPdMm < 85) scan.front.push(a);
            const live = median(scan.front.map((f) => f.nearPdMm * (f.distMm + EYE_ROT_MM) / f.distMm));
            text(E.pillValue, isFinite(live) ? `~${fmt(live)} mm` : '…');
            coach(`Hold still… ${Math.min(100, Math.round(scan.front.length / NEED_FRONT * 100))}%`);
            paintStep(scan.front.length / NEED_FRONT);
            if (scan.front.length >= NEED_FRONT) {
                const s = scan.front.map((f) => f.nearPdMm).sort((x, y) => x - y);
                if (quantile(s, 0.75) - quantile(s, 0.25) > 2.5) {
                    scan.front = scan.front.slice(Math.floor(scan.front.length / 2));
                    coach('Almost — hold still a moment longer.');
                    return;
                }
                if (scan.skipTurns) { finish(); return; }
                scan.step = 'left';
                scan.stepStarted = performance.now();
                E.guide.classList.remove('is-good');
                coach('Now slowly turn your head to the LEFT until the arrow fills.');
                paintStep(0);
            }
            return;
        }

        if (scan.step === 'left' || scan.step === 'right') {
            const sideSign = scan.step === 'left' ? 1 : -1;
            const bucket = scan.step === 'left' ? scan.left : scan.right;
            const turned = a.yaw * sideSign; // positive when turning the requested way
            const bad = gate(a, scan.step);
            if (bad) { coach(bad); paintStep(turned / TURN_TARGET); return; }
            if (turned >= TURN_MIN && turned <= TURN_MAX) {
                bucket.push(a);
                if (scan.step === 'left') scan.maxLeft = Math.max(scan.maxLeft, turned);
                else scan.maxRight = Math.max(scan.maxRight, turned);
            }
            const reached = (scan.step === 'left' ? scan.maxLeft : scan.maxRight) >= TURN_TARGET;
            const progress = Math.min(1, Math.max(turned, 0) / TURN_TARGET);
            E.guide.classList.toggle('is-good', reached);
            paintStep(progress);
            if (turned < -TURN_MIN) coach(`Other way — turn your head to the ${scan.step.toUpperCase()}.`);
            else if (!reached) coach(`Keep turning ${scan.step}… ${Math.round(progress * 100)}%`);
            else coach('Great — now slowly come back to the centre.');

            const done = reached && bucket.length >= NEED_SIDE && Math.abs(a.yaw) < 12;
            const timedOut = elapsed > STEP_TIMEOUT;
            if (done || timedOut) {
                if (scan.step === 'left') {
                    scan.step = 'right';
                    scan.stepStarted = performance.now();
                    coach('Now slowly turn your head to the RIGHT.');
                    paintStep(0);
                } else {
                    finish();
                }
            }
        }
    }

    // ---- fusion + measurement ----
    function finish() {
        const s = scan;
        scan = null;
        stopLoop();
        coach('Calculating…');
        try {
            const r = computeMeasurements(s);
            result = r;
            persist(r);
            paintLocked();
            if (onLocked) onLocked(r);
        } catch (e) {
            console.warn('PD compute failed', e);
            coach('Could not compute — please try again.');
            setTimeout(restart, 1200);
        }
    }

    function computeMeasurements(s) {
        const frontal = s.front;
        if (frontal.length < 8) throw new Error('not enough frontal frames');
        const sides = s.left.concat(s.right);

        // 1) frontal reference, then align every frame to it (two passes for stability)
        let ref = medianShape(frontal.map((f) => f.shape));
        const refMesh = ref.subarray(0, LM.MESH_COUNT * 3);
        const alignAll = (frames) => frames.map((f) => {
            const R = hornRotation(f.shape.subarray(0, LM.MESH_COUNT * 3), refMesh, LM.MESH_COUNT);
            const out = applyRotation(R, f.shape, new Float32Array(f.shape.length));
            return { aligned: out, angle: rotationAngleDeg(R), f };
        });
        let alignedFront = alignAll(frontal);
        ref = medianShape(alignedFront.map((x) => x.aligned));
        alignedFront = alignAll(frontal);
        const alignedSides = alignAll(sides).filter((x) => x.angle >= 8 && x.angle <= 60);
        const fused = medianShape(alignedFront.concat(alignedSides).map((x) => x.aligned));

        // 2) metric scale from the frontal iris (shape units → mm)
        const irisUnits = median(frontal.map((f) => f.irisUnits));
        const mmPerUnit = (IRIS_MM / irisUnits) * CALIB;
        const M = (idx) => mul(pt(fused, idx), mmPerUnit);

        // 3) PD from eye rotation centres (convergence-free distance PD)
        const irisR = M(LM.IRIS_R), irisL = M(LM.IRIS_L);
        const cam = mul([
            median(frontal.map((f) => f.camUnits[0])),
            median(frontal.map((f) => f.camUnits[1])),
            median(frontal.map((f) => f.camUnits[2])),
        ], mmPerUnit);
        const rotR = add(irisR, mul(norm(sub(irisR, cam)), EYE_ROT_MM));
        const rotL = add(irisL, mul(norm(sub(irisL, cam)), EYE_ROT_MM));
        const pd = d3(rotR, rotL);
        const axis = norm(sub(rotL, rotR));
        const nasion = M(LM.NASION);
        let od = dot(sub(nasion, rotR), axis);
        od = clamp(od, pd * 0.4, pd * 0.6);
        const os = pd - od;
        const nearPd = pd * 400 / (400 + EYE_ROT_MM);

        // 4) face metrics
        const faceWidth = d3(M(LM.CHEEK_R), M(LM.CHEEK_L));
        const bridge = d3(M(LM.EYE_R_INNER), M(LM.EYE_L_INNER));
        const faceHeight = d3(M(LM.FOREHEAD), M(LM.CHIN));

        // 5) error estimate: iris prior (±0.5/11.7) ⊕ frame scatter ⊕ landmark bias
        const scatter = mad(frontal.map((f) => f.nearPdMm));
        const err = Math.sqrt((0.043 * pd) ** 2 + scatter ** 2 + 0.5 ** 2);

        const round = (v) => Math.round(v * 2) / 2;
        return {
            pd: round(pd), od: round(od), os: round(os), nearPd: round(nearPd),
            faceWidth: Math.round(faceWidth), bridge: round(bridge), faceHeight: Math.round(faceHeight),
            err: round(err),
            views: { front: alignedFront.length, left: s.left.length, right: s.right.length },
            quick: alignedSides.length < NEED_SIDE,
            at: Date.now(),
        };
    }

    // ---- painting ----
    function coach(msg) { text(E.coach, msg || ''); }

    function paintLocked() {
        E.pill.classList.add('is-locked');
        E.pill.classList.toggle('is-manual', !!result.manual);
        text(E.pillValue, `${fmt(result.pd)} mm`);
        E.guide.classList.add('is-off');
        E.guide.classList.remove('is-good');
        hideEyes();
        coach('');
        text(E.steps, '');
        show(E.arrowLeft, false); show(E.arrowRight, false); show(E.skip, false);
        if (E.progress) E.progress.style.width = '0%';
        if (E.manualInput) E.manualInput.value = '';
        show(E.actions, visible);
        paintResults();
        emit('locked');
    }

    function paintMeasuring() {
        E.pill.classList.remove('is-locked', 'is-manual');
        text(E.pillValue, '…');
        E.guide.classList.remove('is-off', 'is-good');
        coach('Glasses hidden while scanning. Face the camera straight, about 40 cm (16 in) away.');
        show(E.actions, false);
        show(E.verdict, false);
        show(E.results, false);
        emit('measuring');
    }

    function paintResults() {
        if (!E.results) return;
        const r = result;
        if (!r || !visible) { show(E.results, false); return; }
        if (r.manual) {
            E.results.innerHTML = `<div class="pd-res"><span>PD (entered)</span><strong>${fmt(r.pd)} mm</strong></div>`;
            show(E.results, true);
            return;
        }
        const row = (k, v) => `<div class="pd-res"><span>${k}</span><strong>${v}</strong></div>`;
        E.results.innerHTML =
            row('Distance PD', `${fmt(r.pd)} mm <small>± ${fmt(r.err)}</small>`) +
            row('Right / Left', `${fmt(r.od)} / ${fmt(r.os)} mm`) +
            row('Near PD', `${fmt(r.nearPd)} mm`) +
            row('Face width', `${r.faceWidth} mm`) +
            row('Bridge (inner eyes)', `${fmt(r.bridge)} mm`) +
            row('Forehead to chin', `${r.faceHeight} mm`) +
            `<div class="pd-res-note">${r.quick ? 'Quick scan (front view only).' : `3-view scan: ${r.views.front} front · ${r.views.left} left · ${r.views.right} right frames.`}</div>`;
        show(E.results, true);
    }

    // ---- loop / camera ----
    function startLoop() {
        if (running || !landmarker || !source) return;
        if (!scan) scan = newScan();
        running = true; lastTs = 0;
        paintStep(0);
        rafId = requestAnimationFrame(tick);
    }
    function stopLoop() {
        running = false;
        if (rafId) cancelAnimationFrame(rafId);
        rafId = null;
    }
    function restart() {
        result = null; scan = null; forget();
        visible = true;
        applyVisibility();
    }
    async function openOwnCamera() {
        ownVideo = document.createElement('video');
        ownVideo.setAttribute('playsinline', ''); ownVideo.muted = true;
        ownStream = await navigator.mediaDevices.getUserMedia(camera);
        ownVideo.srcObject = ownStream;
        await ownVideo.play().catch(() => {});
        return ownVideo;
    }
    function closeOwnCamera() {
        if (ownStream) { ownStream.getTracks().forEach((t) => t.stop()); ownStream = null; }
        if (ownVideo) { ownVideo.srcObject = null; ownVideo = null; }
    }

    function applyVisibility() {
        show(E.layer, visible);
        if (E.toggle) {
            E.toggle.textContent = visible ? 'Hide PD' : 'Show PD';
            E.toggle.setAttribute('aria-pressed', visible ? 'true' : 'false');
        }
        if (!visible) {
            show(E.verdict, false); show(E.actions, false); show(E.results, false);
            stopLoop();
            emit('hidden');
        } else if (result) {
            paintLocked();
            if (onLocked) onLocked(result);
        } else {
            paintMeasuring();
            startLoop();
        }
    }

    // ---- controls ----
    if (E.toggle) E.toggle.addEventListener('click', () => { visible = !visible; applyVisibility(); });
    if (E.remeasure) E.remeasure.addEventListener('click', restart);
    if (E.skip) E.skip.addEventListener('click', () => {
        if (!scan) return;
        if (scan.step === 'front') { scan.skipTurns = true; return; }
        finish();
    });
    if (E.manualForm) E.manualForm.addEventListener('submit', (e) => {
        e.preventDefault();
        const v = parseFloat(String(E.manualInput.value).replace(',', '.'));
        if (!isFinite(v) || v < 40 || v > 90) {
            E.manualInput.focus();
            E.manualInput.setCustomValidity('Enter a PD between 40 and 90 mm');
            E.manualInput.reportValidity();
            E.manualInput.setCustomValidity('');
            return;
        }
        stopLoop(); scan = null;
        result = { pd: Math.round(v * 2) / 2, at: Date.now(), manual: true };
        persist(result);
        visible = true;
        applyVisibility();
    });

    // ---- verdict vs. product ----
    function renderVerdict(frame) {
        if (!E.verdict) return;
        if (!result || !visible) { show(E.verdict, false); return; }
        const r = result;
        const num = (v) => (v === null || v === undefined || v === '' ? null : parseFloat(v));
        let min = frame ? num(frame.pd_fit_min_mm) : null;
        let max = frame ? num(frame.pd_fit_max_mm) : null;
        const lens = frame ? num(frame.lens_width_mm) : null;
        const bridge = frame ? num(frame.bridge_width_mm) : null;
        let auto = false;
        if (min === null && max === null && lens && bridge) {
            // Frame PD (geometric centre distance) = lens + bridge. Wearer PD a few mm under
            // the frame PD is normal (lenses are decentred inward); far above it is a poor fit.
            const gcd = lens + bridge;
            min = gcd - 8; max = gcd + 2; auto = true;
        }
        const pdLabel = `${fmt(r.pd)} mm${r.manual ? ' (entered)' : ''}`;
        const lines = [];
        let cls = 'is-good';

        if (min === null && max === null) {
            cls = 'is-neutral';
            lines.push(`Your PD is <strong>${pdLabel}</strong>.<small>Fit range is not set for this frame yet, so we can't confirm the fit.</small>`);
        } else {
            const TOL = 0.5;
            const range = `${min !== null ? fmt(min) : '…'}–${max !== null ? fmt(max) : '…'} mm${auto ? ' (from frame size)' : ''}`;
            if (min !== null && r.pd < min - TOL) {
                cls = 'is-big';
                lines.push(`This frame looks <strong>too big</strong> for your PD.<small>Your PD ${pdLabel} · frame fits ${range}. Try a narrower frame.</small>`);
            } else if (max !== null && r.pd > max + TOL) {
                cls = 'is-small';
                lines.push(`This frame looks <strong>too small</strong> for your PD.<small>Your PD ${pdLabel} · frame fits ${range}. Try a wider frame.</small>`);
            } else {
                lines.push(`<strong>Fits your PD.</strong> Your PD ${pdLabel} is within this frame's ${range} range.`);
            }
        }

        // Overall width vs. face width (only with a real scan and frame size on the product)
        if (!r.manual && r.faceWidth && lens && bridge) {
            const frameWidth = 2 * lens + bridge + 10; // + end pieces / rims
            const diff = frameWidth - r.faceWidth;
            if (diff > 8) {
                if (cls === 'is-good') cls = 'is-big';
                lines.push(`<small>Frame front ≈ ${Math.round(frameWidth)} mm is wider than your face (${r.faceWidth} mm) — may look oversized.</small>`);
            } else if (diff < -8) {
                if (cls === 'is-good') cls = 'is-small';
                lines.push(`<small>Frame front ≈ ${Math.round(frameWidth)} mm is narrower than your face (${r.faceWidth} mm) — temples may pinch.</small>`);
            } else {
                lines.push(`<small>Frame width ≈ ${Math.round(frameWidth)} mm matches your face width (${r.faceWidth} mm).</small>`);
            }
        }

        E.verdict.className = `pd-verdict ${cls}`;
        E.verdict.innerHTML = lines.join('');
        show(E.verdict, true);
    }

    // ---- public ----
    async function start(videoEl, lockedCb, stateCb) {
        if (!enabled) return false;
        onLocked = lockedCb || null;
        onState = stateCb || null;
        show(E.toggle, true);
        visible = true;
        scan = null;
        result = restore();
        show(E.layer, true);
        text(E.pillValue, '…');
        coach('Loading face scanner…');
        try {
            await load();
            source = videoEl || await openOwnCamera();
        } catch (e) {
            console.warn('PD scanner unavailable', e);
            show(E.layer, false); show(E.toggle, false);
            return false;
        }
        applyVisibility();
        return true;
    }

    function stop() {
        stopLoop();
        closeOwnCamera();
        source = null; onLocked = null; onState = null; scan = null;
        show(E.layer, false); show(E.toggle, false); show(E.verdict, false);
        show(E.actions, false); show(E.results, false);
        E.pill.classList.remove('is-locked', 'is-manual');
        E.guide.classList.remove('is-off', 'is-good');
        hideEyes();
    }

    return {
        enabled,
        start,
        stop,
        renderVerdict,
        hasStored: () => restore() !== null,
        willMeasure: () => enabled && restore() === null,
    };
}
