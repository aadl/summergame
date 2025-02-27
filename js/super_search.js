const playerRedeem = document.getElementById('summergame-player-redeem-form');
const ssHTML = '<div id="ss-container" class="ss-container"><canvas id="triangleCanvas"></canvas><div class="tray"><div class="action-tray"><button id="ss-guess">Guess</button><button id="ss-clear">Clear</button></div><div class="zoom-tray"><button id="ss-zoom-in">+</button><button id="ss-zoom-out">-</button></div></div><div id="ss-result"></div></div><div class="ss-categories"><h2>Categories & Hints</h2><p>Completed hints will appear with a strikethrough.</p><ol id="ss-hints"></ol></div>'
playerRedeem.insertAdjacentHTML('afterend', ssHTML);
const canvas = document.getElementById('triangleCanvas');
const canvasContainer = document.getElementById('ss-container');
const ctx = canvas.getContext('2d');
const colors = ['rgb(250, 9, 5, 0.3)', 'rgb(0, 14, 250, 0.3)', 'rgb(250, 79, 89, 0.3)', 'rgb(250, 199, 0, 0.3)', 'rgb(165, 60, 58, 0.3)', 'rgb(250, 6, 5, 0.3)', 'rgb(227, 0, 250, 0.3)']
const n = 7;
let correctSet = [];
let correctIds = [];
let correct = [];
const tripoints = [];
let tiles = [];
let letters, categories;
const base = canvasContainer.offsetWidth - 10;
canvas.style.width = `${base}px`;
canvas.style.height = `${base}px`;
canvas.width = base;
canvas.height = base;
let matrix = null;
let invertedMatrix = null;
let needsInversion = false;

const selected = {
	_count: 0,
	_tiles: null,
	get count() {
		return this._count;
	},
	set count(value) {
		this._count = value;
	},
	get tiles() {
		return this._tiles;
	},
	set tiles(value) {
		this._tiles = value
		this._count = value.length
	}
};
const transform = {
	_zoom: 1,
	_offsetX: 0,
	_offsetY: 0,
	get zoom() {
		return this._zoom
	},
	set zoom(v) {
		this._zoom = v
	},
	get offsetX() {
		return Number.isNaN(this._offsetX) ? 0 : this._offsetX
	},
	set offsetX(v) {
		this._offsetX = v
	},
	get offsetY() {
		return Number.isNaN(this._offsetY) ? 0 : this._offsetY
	},
	set offsetY(v) {
		this._offsetY = v
	}
}
const centerX = (canvas.width / 2) * transform.zoom;
const centerY = (canvas.height / 2) * transform.zoom;
const triangleSide = parseInt(((canvas.width * transform.zoom / 12)))
const triangleHeight = Math.sqrt(3) / 2 * triangleSide;
function getTriangleCenter(vertices) {
	const center = {
		x: (vertices[0].x + vertices[1].x + vertices[2].x) / 3,
		y: (vertices[0].y + vertices[1].y + vertices[2].y) / 3
	};
	return center;
}
function drawTriangle(tile) {
	ctx.save();
	ctx.beginPath();
	ctx.moveTo(tile.vertices[0].x, tile.vertices[0].y);
	ctx.lineTo(tile.vertices[1].x, tile.vertices[1].y);
	ctx.lineTo(tile.vertices[2].x, tile.vertices[2].y);
	ctx.closePath();

	if (tile.selected == true) {
		ctx.fillStyle = "#bbf7d0"
	} else if (tile.eligible == false) {
		ctx.fillStyle = "rgba(0,0,0,.3)";
	} else {
		ctx.fillStyle = "rgba(255,255,255,.4)";
	}
	ctx.fill();
	ctx.stroke();
	ctx.restore();
	ctx.font = "400 " + Math.ceil(base / 30) + "px sans-serif";
	const textOffsetX = tile.l == 'I' ? tile.center.x - Math.ceil(base / 150) : tile.center.x - Math.ceil(base / 75);
	const textOffsetY = tile.center.y + Math.ceil(base / 75);
	ctx.fillText(tile.l, textOffsetX, textOffsetY);
}

function generateCoordinates() {
	for (let q = -n + 1; q < n; q++) {
		for (let r = Math.max(-n + 1, -q - n + 1); r < Math.min(n, -q + n); r++) {
			const x = centerX + (q - r) * triangleSide * 0.5;
			const y = centerY + (q + r) * triangleHeight;
			// down tile
			if (q != -n + 1 && r != -n + 1 && q + r != -n + 1) {
				const vertices = [
					{ x: x, y: parseInt(y) },
					{ x: x + triangleSide / 2, y: parseInt((y - triangleHeight)) },
					{ x: x - triangleSide / 2, y: parseInt((y - triangleHeight)) }
				]
				const center = getTriangleCenter(vertices);
				tripoints.push({
					center: center,
					vertices: vertices
				})
			}
			// up tile
			if (q != n - 1 && r != n - 1 && q + r != n - 1) {
				const vertices = [
					{ x: x, y: parseInt(y) },
					{ x: x + triangleSide / 2, y: parseInt((y + triangleHeight)) },
					{ x: x - triangleSide / 2, y: parseInt((y + triangleHeight)) }
				]
				const center = getTriangleCenter(vertices);
				tripoints.push({
					center: center,
					vertices: vertices
				})
			}
		}
	}
}

function findTopLeft(vertices) {
	return vertices.reduce((topLeft, vertex) => {
		if (
			vertex.x + vertex.y < topLeft.x + topLeft.y
		) {
			return vertex;
		}
		return topLeft;
	}, vertices[0]);
}
function orderTiles() {
	tripoints.sort((a, b) => a.center.y - b.center.y);
	let r = [];
	let rows = [];
	let rowY = tripoints[0].center.y;
	const tolerance = triangleHeight / 2;
	tripoints.forEach((tile) => {
		if (Math.abs(tile.center.y - rowY) > tolerance) {
			rows.push(r);
			r = [];
			rowY = tile.center.y;
		}
		r.push(tile);
	});
	// last set
	rows.push(r);

	rows.forEach((row) => row.sort((a, b) => a.center.x - b.center.x));
	tiles = rows.flat();
	for (let i = 0; i < tiles.length; i++) {
		tiles[i].id = i;
		tiles[i].l = letters[i];
		tiles[i].selected = false;
		tiles[i].eligible = true;
	}

}
function drawCorrect(set) {
	bounds = identifyBounds(set.tiles).flat();
	if (set.color != undefined) {
		ctx.save();
		ctx.fillStyle = set.color;
		ctx.strokeStyle = set.color.replace(/[\d\.]+\)$/g, '.4)');
		ctx.lineWidth = 3;
		ctx.beginPath();
		const centroid = bounds.reduce(
			(acc, v) => {
				acc.x += v.x;
				acc.y += v.y;
				return acc;
			},
			{ x: 0, y: 0 }
		);

		centroid.x /= bounds.length;
		centroid.y /= bounds.length;

		const sorted = bounds.flat().sort((a, b) => {
			const angleA = Math.atan2(a.y - centroid.y, a.x - centroid.x);
			const angleB = Math.atan2(b.y - centroid.y, b.x - centroid.x);
			return angleA - angleB;
		});
		for (let i = 0; i < sorted.length; i++) {
			ctx.lineTo(sorted[i].x, sorted[i].y);
		}
		ctx.closePath();
		ctx.stroke();
		ctx.fill();
		ctx.restore();
	}
}
function drawPuzzle() {
	ctx.clearRect(0, 0, canvas.width, canvas.height);
	for (const tile of tiles) {
		drawTriangle(tile);
	}
	if (selected.count === 0) {
		for (const set of correct) {
			drawCorrect(set)
		}
	}
}
function applyTransforms() {
	matrix = new DOMMatrix();
	matrix.translateSelf(transform.offsetX, transform.offsetY);
	matrix.scaleSelf(transform.zoom, transform.zoom);
	ctx.clearRect(0, 0, canvas.width, canvas.height);
	ctx.setTransform(matrix);
	needsInversion = true;
	drawPuzzle();
}
function defineEligible() {
	bounds = identifyBounds(selected.tiles);
	const eIds = []
	if (selected.tiles.length === 1) {
		tiles.forEach((t) => {
			let i = 0;
			const containsTwoPoints = t.vertices.some((v) => {
				for (p of bounds[0]) {
					if (Object.keys(v).every(key => v[key] === p[key])) {
						i++;
					}
				}
				if (i === 2) {
					return true;
				}
				return false
			})
			if (containsTwoPoints) {
				eIds.push(t.id)
			}
		});
	} else {
		tiles.forEach((t) => {
			const containsAcutePoint = t.vertices.some((v) => {
				for (p of bounds[0]) {
					if (Object.keys(v).every(key => v[key] === p[key])) {
						return true;
					}
				}
				return false;
			})
			const containsObtusePoint = t.vertices.some((v) => {
				for (p of bounds[1]) {
					if (Object.keys(v).every(key => v[key] === p[key])) {
						return true;
					}
				}
				return false;
			})
			if ((containsObtusePoint && containsAcutePoint)) {
				eIds.push(t.id)
			}
		});
	}
	const ineligible = tiles.filter((t) => !eIds.includes(t.id));
	const eligible = tiles.filter((t) => eIds.includes(t.id));
	eligible.forEach((e) => e.eligible = true)
	if (eligible.length === 0) {
		ineligible.forEach((i) => i.eligible = true)
	} else {
		ineligible.forEach((i) => i.eligible = false)
	}
}
function handleSelection(event) {
	const rect = canvas.getBoundingClientRect();
	const point = {
		x: (event.clientX - rect.left) * (canvas.width / rect.width),
		y: (event.clientY - rect.top) * (canvas.height / rect.height)
	};
	let pt = null;
	if (needsInversion) {
		invertedMatrix = matrix.inverse();
		pt = new DOMPoint(point.x, point.y).matrixTransform(invertedMatrix)
	} else {
		pt = point;
	}
	for (const tile of tiles) {
		const [v1, v2, v3] = tile.vertices.map((v) => {
			return new DOMPoint(v.x, v.y).matrixTransform(matrix);
		});
		if (isPointInTriangle(point, v1, v2, v3)) {
			if (tile.eligible) {
				tile.selected = !tile.selected;
				selected.tiles = tiles.filter((t) => t.selected == true);
				defineEligible();
				drawPuzzle();
			}
		}
	}
}
function identifyBounds(set) {
	let max = 0;
	let min = Infinity;
	let candidate;

	const counts = {};
	const acute = {
		coords: [],
		count: []
	};
	const obtuse = {
		coords: [],
		count: []
	};
	set.forEach((t) => {
		t.vertices.forEach((c) => {
			const coord = c.x + ',' + c.y;
			counts[coord] = (counts[coord] || 0) + 1;
		})
	});
	set.forEach((t) => {
		t.vertices.forEach((c) => {
			const coord = c.x + ',' + c.y;
			if (counts[coord] === 2 && !obtuse.count.includes(coord)) {
				obtuse.count.push(coord)
				obtuse.coords.push({ x: c.x, y: c.y });
			}
			if (counts[coord] === 1 && !acute.count.includes(coord)) {
				acute.count.push(coord)
				acute.coords.push({ x: c.x, y: c.y });
			}
		})
	});
	return [acute.coords, obtuse.coords]
}
function isPointInTriangle(pt, v1, v2, v3) {
	const area = (v1, v2, v3) =>
		Math.abs((v1.x * (v2.y - v3.y) + v2.x * (v3.y - v1.y) + v3.x * (v1.y - v2.y)) / 2);
	const A = area(v1, v2, v3);
	const A1 = area(pt, v2, v3);
	const A2 = area(v1, pt, v3);
	const A3 = area(v1, v2, pt);
	return Math.abs(A - (A1 + A2 + A3)) < 0.01;
}
function zoomIn() {
	if (transform.zoom < 1.6) {
		transform.zoom += .1;
		applyTransforms();
	}
}
function zoomOut() {
	if (transform.zoom > .9) {
		transform.zoom -= .1;
		applyTransforms()
	}
}

let isDragging = false;
let isClick = true;
let lastX, lastY, dt;
const t = 6;

function dragTransform(e) {
	if (isClick) {
		const pos = event.type.startsWith("touch") ? getTouchV(event) : getMouseV(event);
		const rect = canvas.getBoundingClientRect();
		let newX = pos.x - rect.left;
		let newY = pos.y - rect.top;
		transform.offsetX = newX - lastX;
		transform.offsetY = newY - lastY;

		if (transform.offsetX > t || transform.offsetY > t || Date.now() - dt > 100) {
			isDragging = true;
			isClick = false;
		}
	}
	if (isDragging) {
		canvas.style.cursor = 'grabbing';
		const pos = event.type.startsWith("touch") ? getTouchV(event) : getMouseV(event);
		const rect = canvas.getBoundingClientRect();
		let newX = pos.x - rect.left;
		let newY = pos.y - rect.top;
		transform.offsetX = newX - lastX;
		transform.offsetY = newY - lastY;
		applyTransforms()
	}
}
function endDrag(event) {
	if (!isDragging && isClick) {
		handleSelection(event)
	}
	canvas.style.cursor = 'auto';
	isDragging = false;
	isClick = false;
}
function startDrag(event) {
	event.preventDefault(); // Prevents touch scrolling
	const pos = event.type.startsWith("touch") ? getTouchV(event) : getMouseV(event);
	const rect = canvas.getBoundingClientRect();
	lastX = pos.x - rect.left;
	lastY = pos.y - rect.top;
	isDragging = false;
	isClick = true;
	dt = Date.now();

}
function getMouseV(event) {
	let rect = canvas.getBoundingClientRect();
	return {
		x: event.clientX - rect.left,
		y: event.clientY - rect.top
	};
}

function getTouchV(event) {
	let rect = canvas.getBoundingClientRect();
	let touch = event.touches[0]; // Get first touch point
	return {
		x: touch.clientX - rect.left,
		y: touch.clientY - rect.top
	};
}
document.addEventListener("DOMContentLoaded", function () {
	if (window.drupalSettings && window.drupalSettings.nid) {
		getPuzzle();

	}
});
async function httpGet(url) {
	const response = await fetch(url, {
		method: "GET",
		headers: {
			Accept: "application/json",
			"Content-Type": "application/json"
		}
	});
	return response.json();
}
async function httpPost(url, body) {
	return fetch(url, {
		method: 'POST',
		cache: 'no-cache',
		credentials: 'same-origin',
		headers: {
			Accept: "application/json",
			"Content-Type": "application/json",
		},
		body: body
	}).then(response => {
		return response.json();
	});
}
async function getPuzzle() {
	const puzzle = httpGet('/summergame/super_search/' + window.drupalSettings.nid + '/get').then((data) => {
		letters = data.letters;
		categories = data.categories;
		const list = document.getElementById('ss-hints')
		let i = 0;
		categories.forEach((c) => {
			let el = document.createElement('li');
			let d = document.createElement('ul');
			d.id = 'ss-revealed-' + i
			let b = document.createElement('button');
			b.id = 'ss-hint-' + i
			b.textContent = "Get Hint";
			b.setAttribute('data-hint-id', i);
			d.appendChild(b);
			el.textContent = c;
			el.appendChild(d);
			list.appendChild(el)
			i++
		})
		orderTiles();
		drawPuzzle();
		const hintBtns = document.querySelectorAll(`[id^='ss-hint-']`);
		hintBtns.forEach((b) => {
			b.addEventListener("click", () => handleHint(b));
		});
	})
}
function handleHint(b) {
	const id = b.getAttribute('data-hint-id');
	const c = document.querySelector('#ss-revealed-' + id);
	const r = c.querySelectorAll('li');
	const revealed = Array.from(r).map(h => { return h.textContent })
	const body = JSON.stringify({ revealed: revealed });
	const hint = httpPost('/summergame/super_search/' + window.drupalSettings.nid + '/hint/' + id, body).then((data) => {
		if (data.hint) {
			const h = document.createElement('li');
			h.textContent = data.hint;
			b.insertAdjacentHTML('beforebegin', h.outerHTML);
		} if (r.length === 5) {
			b.style.display = 'none';
		}
	});
}
function handleGuess() {
	const ids = selected.tiles.map((t) => { return t.id });
	const body = JSON.stringify({ ids: ids });
	const res = document.querySelector('#ss-result');
	const guess = httpPost('/summergame/super_search/' + window.drupalSettings.nid + '/guess', body).then((data) => {
		if (data.correct) {
			const c = {
				tiles: selected.tiles,
				color: data.color
			}
			res.textContent = 'Correct!';
			correct.push(c);
			completeHint(data.category, data.hint);
			handleClear();
			drawPuzzle();
		} else {
			res.textContent = 'Not quite';
		}
		res.style.display = 'block'
		setTimeout(() => {
			res.style.display = 'none';
		}, 4000);
	});
}
function handleClear() {
	tiles = tiles.map((t) => {
		t.selected = false
		t.eligible = true;
		return t;
	})
	selected.tiles = [];
	drawPuzzle();
}
function completeHint(i, h) {
	const c = document.querySelector('#ss-revealed-' + i);
	const r = c.querySelectorAll('li');
	if (r.length > 0) {
		const f = Array.from(r).filter((l) => { return l.textContent === h });
		f[0].style.textDecoration = 'line-through'
	} else {
		const d = document.createElement('li');
		const b = document.querySelector('[data-hint-id="' + i + '"]');
		d.textContent = h;
		d.style.textDecoration = 'line-through';
		b.insertAdjacentHTML('beforebegin', d.outerHTML);
	}

}
const zin = document.getElementById('ss-zoom-in');
const zout = document.getElementById('ss-zoom-out');
const guess = document.getElementById('ss-guess');
const clear = document.getElementById('ss-clear');

zin.addEventListener('click', zoomIn);
zout.addEventListener('click', zoomOut);
guess.addEventListener('click', handleGuess);
clear.addEventListener('click', handleClear);
generateCoordinates();

// drags
canvas.addEventListener("mousedown", startDrag);
canvas.addEventListener("mousemove", dragTransform);
canvas.addEventListener("mouseup", endDrag);
canvas.addEventListener("mouseleave", endDrag);
canvas.addEventListener("touchstart", startDrag);
canvas.addEventListener("touchmove", dragTransform);
canvas.addEventListener("touchend", endDrag);
canvas.addEventListener("touchcancel", endDrag);

