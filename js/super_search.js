const playerRedeem = document.getElementById('badge-progress');
const ssHTML = '<div id="ss-container" class="ss-container"><canvas id="triangleCanvas"></canvas><div class="tray"><div class="action-tray"><button id="ss-guess">Guess</button><button id="ss-clear">Clear</button></div><div class="zoom-tray"><button id="ss-zoom-in">+</button><button id="ss-zoom-out">-</button></div></div><div id="ss-result"></div><div class="ss-ui-hint">Hint: once a tile is selected, you may tap/click and drag across to select multiple tiles. Clear the selection to reposition the puzzle.</div></div><div class="ss-categories"><h2>Categories & Hints</h2><p>Completed hints will appear with a strikethrough.</p><ol id="ss-hints"></ol></div>'
playerRedeem.insertAdjacentHTML('beforeBegin', ssHTML);
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
const dpr = window.devicePixelRatio || 1;
let matrix = null;
let invertedMatrix = null;
let needsInversion = false;
let centerX, centerY, triangleSide, triangleHeight

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

let isSolved = false;
let isDragging = false;
let isClick = true;
let isMulti = false;
let lastX, lastY, dt;
const t = 6;
//svg animation stuff
const batchSize = 1;
let currentIndex = 0;
let pathObjects;
sizeCanvas();
function sizeCanvas() {
	const width = canvas.offsetWidth;
	canvas.style.height = `${width}px`;
	canvas.width = width * dpr;
	canvas.height = width * dpr;
	centerX = (canvas.width / 2) * transform.zoom;
	centerY = (canvas.height / 2) * transform.zoom;
	triangleSide = parseInt(((canvas.width * transform.zoom / 12)))
	triangleHeight = Math.sqrt(3) / 2 * triangleSide;
	ctx.scale(dpr, dpr);
}
function getTriangleCenter(vertices) {
	const center = {
		x: (vertices[0].x + vertices[1].x + vertices[2].x) / 3,
		y: (vertices[0].y + vertices[1].y + vertices[2].y) / 3
	};
	return center;
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
function sortHexPoints(points) {
	const center = {
		x: points.reduce((sum, p) => sum + p.x, 0) / points.length,
		y: points.reduce((sum, p) => sum + p.y, 0) / points.length,
	};

	return points.slice().sort((a, b) => {
		const angleA = Math.atan2(a.y - center.y, a.x - center.x);
		const angleB = Math.atan2(b.y - center.y, b.x - center.x);
		return angleA - angleB;
	});
}
function applyShadow() {
	const hexCount = new Map();
	const triSet = tiles.map((t) => { return t.vertices });
	triSet.flat().forEach(({ x, y }) => {
		const key = `${x},${y}`;
		hexCount.set(key, (hexCount.get(key) || 0) + 1);
	});
	const hexVertices = [...hexCount.entries()]
		.filter(([_, count]) => count <= 2)
		.map(([key]) => {
			const [x, y] = key.split(',').map(Number);
			return { x, y };
		});
	const sortedVertices = sortHexPoints(hexVertices);
	ctx.save();
	ctx.shadowColor = 'rgba(0, 0, 0, 0.8)';
	ctx.shadowOffsetX = 12;
	ctx.shadowOffsetY = 5;
	ctx.shadowBlur = 10;
	ctx.moveTo(sortedVertices[0].x, sortedVertices[0].y);
	ctx.beginPath();
	sortedVertices.forEach((v) => {
		ctx.lineTo(v.x, v.y);
	});
	ctx.fill();
	ctx.closePath();
	ctx.restore();
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
function drawTriangle(tile) {
	ctx.save();
	ctx.beginPath();
	ctx.moveTo(tile.vertices[0].x, tile.vertices[0].y);
	ctx.lineTo(tile.vertices[1].x, tile.vertices[1].y);
	ctx.lineTo(tile.vertices[2].x, tile.vertices[2].y);
	ctx.closePath();
	ctx.lineWidth = 1;
	if (tile.selected == true) {
		ctx.lineWidth = 1;
		ctx.fillStyle = "#000"
		ctx.strokeStyle = "#fff"
	} else if (tile.eligible == false) {
		ctx.fillStyle = "#71717a";
	} else {
		ctx.lineWidth = 3;
		ctx.fillStyle = "#fff";
	}
	ctx.stroke();
	ctx.fill();
	ctx.restore()
}
function drawText(tile) {
	ctx.textAlign = 'center';
	if (tile.selected == true) {
		ctx.fillStyle = "#fff"
	} else {
		ctx.fillStyle = "#000"
	}
	ctx.font = "400 1.2em sans-serif";
	const textOffsetY = tile.center.y + (canvas.height * 0.01);
	ctx.fillText(tile.l, tile.center.x, textOffsetY);
}
function drawCorrect(set) {
	bounds = identifyBounds(set.tiles).flat();
	if (set.color != undefined) {
		ctx.save();
		ctx.fillStyle = set.color;
		ctx.strokeStyle = set.color.replace(/[\d\.]+\)$/g, '.4)');
		ctx.lineWidth = 5;
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
	ctx.clearRect(0, 0, canvas.width + 60, canvas.height + 60);
	applyShadow()
	for (const tile of tiles) {
		drawTriangle(tile);
	}
	if (selected.count === 0) {
		for (const set of correct) {
			drawCorrect(set)
		}
	}
	for (const tile of tiles) {
		drawText(tile);
	}

}
async function drawPrompt(a) {
	isSolved = true;
	const prompt = document.createElement('template');
	const svg = await fetch('/modules/custom/summergame/images/SG2025-WebGraphic-sm.svg');
	const svgText = await svg.text();
	const parser = new DOMParser();
	const svgDoc = parser.parseFromString(svgText, "image/svg+xml");
	const svgElement = svgDoc.documentElement;
	svgElement.style = 'hidden';
	const svgPaths = Array.from(svgElement.querySelectorAll("path"));
	svgPaths.forEach((path, i) => {
		const length = path.getTotalLength();
		const fill = path.dataset.ssColor;
		path.style.setProperty('--path-length', length);
		path.style.setProperty('--stroke-color', '#301d2b');
		path.style.setProperty('--fill-color', fill);
		if (path.dataset.ssSkip != 1) {
			path.style.setProperty('--draw-duration', '0.6s');
			if (i === 2) {
				prompt.innerHTML = a;
				const promptContent = prompt.content.cloneNode(true);
				canvasContainer.appendChild(promptContent);
				document.querySelector('.win-prompt > p').insertAdjacentElement('beforeBegin', svgElement);
				tray.classList.add('win');
			}
			setTimeout(() => {
				path.classList.add('write');
				path.style.visibility = 'visible';
			}, i * 200);
		} else {
			setTimeout(() => {
				const fill = path.dataset.ssColor;
				path.style.setProperty('--fill-color', fill);
				path.classList.add('write');
				path.style.visibility = 'visible';
			}, length * 200);
		}

	});
	setTimeout(() => {
		svgElement.classList.add('bulge')
	}, 4000);


}
function applyTransforms() {
	matrix = new DOMMatrix();
	matrix.translateSelf(transform.offsetX, transform.offsetY);
	matrix.scaleSelf(transform.zoom, transform.zoom);
	ctx.clearRect(0, 0, canvas.width, canvas.height);
	ctx.setTransform(matrix);
	needsInversion = true;
	drawPuzzle();
	ctx.scale(dpr, dpr);
	const bgX = (-transform.offsetX * 0.05) - (canvas.width * .03);
	const bgY = (-transform.offsetY * 0.05) - (canvas.height * .03);
	canvas.style.backgroundPosition = `${bgX}px ${bgY}px`;
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
	if (!isSolved) {
		const rect = canvas.getBoundingClientRect();
		const ptX = isTouchDevice() ? (event.changedTouches[0].clientX - rect.left) * (canvas.width / rect.width) : (event.clientX - rect.left) * (canvas.width / rect.width);
		const ptY = isTouchDevice() ? (event.changedTouches[0].clientY - rect.top) * (canvas.height / rect.height) : (event.clientY - rect.top) * (canvas.height / rect.height);
		const point = {
			x: ptX,
			y: ptY
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
					tile.selected = isMulti ? true : !tile.selected;
					selected.tiles = tiles.filter((t) => t.selected == true);
					defineEligible();
					applyTransforms();
				}
			}
		}
		if (selected.count > 0) {
			uiHint.style.visibility = 'visible';
		} else {

			uiHint.style.visibility = 'hidden';
		} isMulti = false;
		isClick = true;
	}
}
function identifyBounds(set) {
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
		centerX = (canvas.width / 2) * transform.zoom;
		centerY = (canvas.height / 2) * transform.zoom;
		applyTransforms();
	}
}
function isTouchDevice() {
	return (('maxTouchPoints' in navigator) && navigator.maxTouchPoints > 0);
}

function zoomOut() {
	if (transform.zoom > .9) {
		transform.zoom -= .1;
		centerX = (canvas.width / 2) * transform.zoom;
		centerY = (canvas.height / 2) * transform.zoom;
		applyTransforms()
	}
}

function dragTransform(e) {
	if (isClick) {
		const pos = e.type.startsWith("touch") ? getTouchV(e) : getMouseV(e);
		const rect = canvas.getBoundingClientRect();
		let newX = (pos.x - rect.left);
		let newY = (pos.y - rect.top);
		if (((newX - lastX) > t || (newY - lastY) > t || Date.now() - dt > 100)) {
			isDragging = true;
			isClick = false;
		}
	}
	if (isDragging && selected.count === 0) {
		canvas.style.cursor = 'grabbing';
		const pos = e.type.startsWith("touch") ? getTouchV(e) : getMouseV(e);
		const rect = canvas.getBoundingClientRect();
		let newX = (pos.x - rect.left) * (canvas.width / rect.width);
		let newY = (pos.y - rect.top) * (canvas.height / rect.height)
		transform.offsetX = (newX - lastX);
		transform.offsetY = (newY - lastY);
		applyTransforms()
	} else if (isDragging) {
		isMulti = true;
		handleSelection(e);
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
	lastX = (pos.x - rect.left) * (canvas.width / rect.width);
	lastY = (pos.y - rect.top) * (canvas.height / rect.height);
	isDragging = false;
	isClick = true;
	dt = Date.now();

}

function getMouseV(event) {
	const rect = canvas.getBoundingClientRect();
	return {
		x: event.clientX - rect.left,
		y: event.clientY - rect.top
	};
}

function getTouchV(event) {
	const rect = canvas.getBoundingClientRect();
	let touch = event.touches[0]; // Get first touch point
	return {
		x: touch.clientX - rect.left,
		y: touch.clientY - rect.top
	};
}
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
		data.progress.forEach((s) => {
			const c = {
				tiles: tiles.filter((t) => s['ids'].includes(t.id)),
				color: s['color']
			}
			correct.push(c);
		});
		data.completedHints.forEach((h) => {
			completeHint(h.category, h.hint, h.word);
		});

		applyTransforms();
		if (data.answer != null) {
			drawPrompt(data.answer)
		}
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
	const revealed = Array.from(r).map(l => { return l.querySelector('.cat-hint').textContent })
	const body = JSON.stringify({ revealed: revealed });
	const hint = httpPost('/summergame/super_search/' + window.drupalSettings.nid + '/hint/' + id, body).then((data) => {
		if (data.hint) {
			if (!revealed.includes(data.hint)) {
				const h = document.createElement('li');
				const sp = '<span class="cat-hint">' + data.hint + '</span>';
				h.innerHTML = sp;
				c.append(h);
			} else {

			}
		} if (r.length === 5) {
			b.disabled = true;
			b.style.backgroundColor = '#ccc'
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
			completeHint(data.category, data.hint, data.word);
			handleClear();
			applyTransforms();
			if (data.answer != null) {
				drawPrompt(data.answer)
			}
		} else {
			res.textContent = 'Incorrect';
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
	applyTransforms();
	uiHint.style.visibility = 'hidden';
}
function completeHint(i, h, w) {
	const cat = document.querySelector('#ss-revealed-' + i);
	const r = cat.querySelectorAll('li');
	const revealed = Array.from(r).map(l => { return l.querySelector('.cat-hint').textContent })
	const b = document.querySelector('[data-hint-id="' + i + '"]');
	const sp = '<span style="font-weight:bold">' + w + ':  </span><span class="cat-hint" style="text-decoration:line-through;">' + h + '</span>';
	if (r.length > 0) {
		if (!revealed.includes(h)) {
			const n = document.createElement('li');
			n.innerHTML = sp;
			cat.appendChild(n);
		} else {
			Array.from(r).filter(l => { return l.querySelector('.cat-hint').textContent == h })[0].innerHTML = sp;
		}
		if (cat.querySelectorAll('li').length === 5) {
			cat.parentNode.style.textDecoration = 'line-through'
			b.style.backgroundColor = '#ccc';
			b.disabled = true;
		}
	} else {
		const d = document.createElement('li');
		d.innerHTML = sp;
		cat.appendChild(d);
	}
}


document.addEventListener("DOMContentLoaded", function () {
	if (window.drupalSettings && window.drupalSettings.nid) {
		getPuzzle();
	}
});
window.addEventListener("resize", () => {
	sizeCanvas();
	applyTransforms();
});
const zin = document.getElementById('ss-zoom-in');
const zout = document.getElementById('ss-zoom-out');
const guess = document.getElementById('ss-guess');
const clear = document.getElementById('ss-clear');
const tray = document.querySelector('.tray');
const uiHint = document.querySelector('.ss-ui-hint');

zin.addEventListener('click', zoomIn);
zout.addEventListener('click', zoomOut);
guess.addEventListener('click', handleGuess);
clear.addEventListener('click', handleClear);
generateCoordinates();

tray.addEventListener('touchmove', (e) => {
	e.preventDefault();
}, { passive: false });

canvas.addEventListener("mousedown", startDrag);
canvas.addEventListener("mousemove", dragTransform);
canvas.addEventListener("mouseup", endDrag);
canvas.addEventListener("mouseleave", endDrag);
canvas.addEventListener("touchstart", startDrag);
canvas.addEventListener("touchmove", dragTransform);
canvas.addEventListener("touchend", endDrag);
canvas.addEventListener("touchcancel", endDrag);

