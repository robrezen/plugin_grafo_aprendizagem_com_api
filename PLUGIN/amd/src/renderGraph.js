define(['core/log'], function(log) {
    return {
        renderGraph: (containerId, graph) => {
            log.debug(`Rendering graph in container ${containerId}`);
            const container = document.getElementById(containerId);
            container.innerHTML = '';
            const graphs = new Map();

            const nodes = new vis.DataSet(
                Object.keys(graph.nodes).map((key) => {
                    const node = graph.nodes[key];
            
                    // Função para calcular a cor com base no nível
                    const getMaterialColor = (level) => {
                        if (level === -1) return { border: '#BDBDBD', background: '#E0E0E0' };
            
                        let red, green, blue = 0;
            
                        if (level <= 255) {
                            // Interpolação de vermelho para amarelo
                            red = 255;
                            green = Math.round((level / 255) * 255);
                        } else {
                            // Interpolação de amarelo para verde
                            red = Math.round(255 - ((level - 255) / 255) * 255);
                            green = 255;
                        }
            
                        return {
                            border: `rgba(${red}, ${green}, ${blue}, 1)`,
                            background: `rgba(${red}, ${green}, ${blue}, 0.7)`,
                        };
                    };
            
                    const color = getMaterialColor(node.level);
            
                    return {
                        id: key,
                        label: key,
                        size: node.importance * 2,
                        color: {
                            border: color.border,
                            background: color.background,
                            highlight: {
                                border: '#FFEB3B',
                                background: '#FFEB3B'
                            },
                            hover: {
                                border: '#FFEB3B',
                                background: '#FFF9C4'
                            }
                        },
                        font: {
                            color: '#212121',
                            size: 14,
                            face: 'Roboto, Arial, sans-serif',
                            background: 'rgba(255,255,255,0.8)',
                            strokeWidth: 0,
                            align: 'center'
                        },
                        shape: 'dot'
                    };
                })
            );
            
            const edges = new vis.DataSet(
                graph.edges.map((edge, index) => {
                    return {
                        from: edge.head,
                        to: edge.tail,
                        label: edge.type,
                        font: {
                            align: 'middle',
                            color: '#757575',
                            size: 12,
                            face: 'Roboto, Arial, sans-serif',
                            strokeWidth: 0,
                            background: 'rgba(255,255,255,0.8)'
                        },
                        color: {
                            inherit: 'from',
                        },
                        arrows: {
                            to: {
                                enabled: true,
                                scaleFactor: 0.5
                            }
                        },
                        smooth: {
                            type: 'straightCross',
                            roundness: 0.2 + (index % 3) * 0.2
                        }
                    };
                })
            );

            const data = {
                nodes: nodes,
                edges: edges
            };

            const options = {
                nodes: {
                    shape: 'dot',
                    scaling: {
                        min: 10,
                        max: 30
                    },
                    borderWidth: 2,
                    shadow: false,
                    font: {
                        size: 12,
                        face: 'Roboto, Arial, sans-serif',
                        color: '#212121'
                    }
                },
                edges: {
                    shadow: false,
    
                },
                layout: {
                    improvedLayout: true,
                    hierarchical: false,
                },
                interaction: {
                    hover: true,
                    tooltipDelay: 200,
                    hideEdgesOnDrag: true,
                    hideEdgesOnZoom: true,
                },
                physics: {
                    enabled: true,
                    barnesHut: {
                        gravitationalConstant: -2000,
                        centralGravity: 0.5,
                        springLength: 150, 
                        springConstant: 0.02,
                        damping: 0.9,
                        avoidOverlap: 1
                    },
                    maxVelocity: 50,
                    minVelocity: 0.1,
                    solver: 'barnesHut',
                    stabilization: {
                        enabled: true,
                        iterations: 2000,
                        updateInterval: 25
                    },
                    timestep: 0.5,
                    adaptiveTimestep: true
                }
            };

            const network = new vis.Network(container, data, options);
  
            network.redraw();
            network.fit();

            if (containerId === 'module-graph-container' || containerId === 'lessonplan-graph-container' || containerId === 'user-graph-container') {
                network.once('afterDrawing', () => {
                    var containerWidth = container.offsetWidth;
                    var containerHeight = container.offsetHeight;
                    var scale = 1;
                    console.log(containerWidth, containerHeight)
                    network.moveTo({
                        offset: {
                            x: (0.5 * containerWidth) * scale,
                            y: (0.5 * containerHeight) * scale
                        },
                        scale: scale
                    });
                });
            }
            graphs.set(containerId, network);
            switch (containerId) {
                case 'lessonplan-graph-container':
                    document.getElementById('fullscreen-btn-lesson').addEventListener('click', () => fullscreenGraph('lessonplan-graph-container'));
                    break;
                case 'module-graph-container':
                    document.getElementById('fullscreen-btn-module').addEventListener('click', () => fullscreenGraph('module-graph-container'));
                    break;
                case 'user-graph-container':
                    document.getElementById('fullscreen-btn-student').addEventListener('click', () => fullscreenGraph('user-graph-container'));
                    break;
            }
            
            function fullscreenGraph(containerId) {
                const network = graphs.get(containerId);
                if (network) {
                    console.debug(`Fitting graph to container ${containerId}`);
                    network.fit({
                        animation: {
                            duration: 500,
                            easingFunction: 'easeInOutQuad'
                        },
                    });
                }
            }
        }
    };
});
