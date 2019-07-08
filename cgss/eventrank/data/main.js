function _(type, props, children) {
	var elem = null;
	if (type === "text") {
		return document.createTextNode(props);
	} else {
		elem = document.createElement(type);
	}
	for (var n in props) {
		if (n === "style") {
			for (var x in props.style) {
				elem.style[x] = props.style[x];
			}
		} else if (n === "className") {
			elem.className = props[n];
		} else if (n === "event") {
			for (var x in props.event) {
				elem.addEventListener(x, props.event[x]);
			}
		} else {
			elem.setAttribute(n, props[n]);
		}
	}
	if (children) {
		for (var i = 0; i < children.length; i++) {
			if (children[i] != null)
				elem.appendChild(children[i]);
		}
	}
	return elem;
};

var timezoneOffset = (new Date).getTimezoneOffset();
var names = {}, list;
function loadedIndex(data) {
	names = data.name;
	list = document.querySelector('select')
	data.available.forEach(function (i) {
		var option = _('option', { value: i }, [_('text', names[i])]);
		if (i == data.current) option.setAttribute('selected', '');
		list.appendChild(option);
	});
	list.firstChild.remove();
	list.addEventListener('change', function () {
		var xhr = new XMLHttpRequest();
		xhr.open('GET', list.value + '.json', true);
		xhr.responseType = 'json';
		xhr.onload = function () { loadedData(xhr.response) };
		xhr.send();
	});
	list.dispatchEvent(new Event('change'));
}
var chart;
function loadedData(data) {
	var axises = [], series = [], keys, yAxis = 0;
	if (data.score) {
		axises.push({
			allowDecimals: false,
			title: {
				text: 'score'
			},
			lineColor: Highcharts.getOptions().colors[yAxis]
		});
		keys = Object.keys(data.score);
		keys.sort(function (a, b) { return a - b; });
		keys.forEach(function (i) {
			series.push({
				name: 'score - ' + i,
				type: 'line',
				yAxis: yAxis,
				marker: {
					enabled: false
				},
				data: data.score[i]
			});
		});
		yAxis++;
	}
	if (data.pt) {
		axises.push({
			allowDecimals: false,
			title: {
				text: 'pt'
			},
			lineColor: Highcharts.getOptions().colors[yAxis],
			opposite: !!yAxis
		});
		keys = Object.keys(data.pt);
		keys.sort(function (a, b) { return a - b; });
		keys.forEach(function (i) {
			series.push({
				name: 'pt - ' + i,
				type: 'line',
				yAxis: yAxis,
				marker: {
					enabled: false
				},
				data: data.pt[i],
				dashStyle: 'Dash'
			});
		});
		yAxis++;
	}
	if (chart) chart.destroy();
	chart = new Highcharts.chart({
		chart: {
			renderTo: document.querySelector('.content'),
			zoomType: "x",
			//backgroundColor: 'transparent'
		},
		credits: {
			enabled: false
		},
		type: 'line',
		xAxis: {
			type: 'datetime',
			dateTimeLabelFormats: {
				//day:'%H:%M',
				day: '%m/%d',
				hour: '%H:%M'
			}
		},
		yAxis: axises,
		title: {
			text: null
		},
		tooltip: {
			formatter: function () {
				var time = new Date(this.x + (timezoneOffset - (-9 * 60))*60*1e3), str = '', check = function (a) { return (a < 10 ? '0' : '') + a };
				str += check(time.getDate()) + ' ' + check(time.getHours()) + ':' + check(time.getMinutes()) + '<br><br><table>';
				if (!this.points) {
					str = check(time.getMonth() + 1) + '/' + check(time.getDate()) + '<br><table>';
					str += '<tr><td style="color:' + this.color + '">' + this.point.name + '</td><td style="text-align:right">' + this.y + '</td></tr>';
					str += '</table>';
					return str;
				}
				this.points.forEach(function (i) {
					str += '<tr><td style="color:' + i.color + '">' + i.series.name + '</td><td style="text-align:right">' + i.y + '</td></tr>';
				});
				str += '</table>';
				return str;
			},
			shared: true,
			crosshairs: true,
			useHTML: true
		},
		series: series,
		exporting: {
			filename: 'DereSute_event_border',
			enabled: true,
			buttons: {
				contextButton: {
					menuItems: [
						'downloadPNG',
						'downloadJPEG',
						'downloadSVG'
					]
				}
			}
		}
	});
	var legendGroupElement = chart.legend.group.element, prevFoundItem = null, prevFoundTime = 0;
	var legendClicker = function (e) {
		var findLegend = e.target, current = performance.now();
		while (legendGroupElement.contains(findLegend)) {
			if (findLegend.classList.contains('highcharts-legend-item')) break;
			findLegend = findLegend.parentNode;
		}
		if (!legendGroupElement.contains(findLegend)) return;
		if (prevFoundItem != null && findLegend == prevFoundItem && current - prevFoundTime <= 500) {
			e.preventDefault();
			e.stopPropagation();
			var currentOn = 0;
			chart.legend.allItems.forEach(function (i) {
				i.visible && ++currentOn;
			});			
			chart.legend.allItems.forEach(function (i) {
				i.setVisible(currentOn <= 1 || i.legendGroup.element == findLegend, false);
			})
			chart.redraw();
			prevFoundItem = null;
			return;
		}
		prevFoundItem = findLegend;
		prevFoundTime = current;
	};
	legendGroupElement.addEventListener('click', legendClicker);
	legendGroupElement.addEventListener('touchend', legendClicker);
}

window.addEventListener('load', function () {
	var xhr = new XMLHttpRequest();
	xhr.open('GET', 'available.json', true);
	xhr.responseType = 'json';
	xhr.onload = function () { loadedIndex(xhr.response); };
	xhr.send();
	Highcharts.setOptions({
		global: {
			timezoneOffset: -9 * 60
		},
		lang: {
			resetZoom: '重置缩放',
			resetZoomTitle: '重置缩放比例至1:1',
			thousandsSep: ','
		}
	});
	document.querySelector('.content').addEventListener('touchmove',function(e){e.preventDefault();})
});

