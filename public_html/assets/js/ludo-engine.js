/**
 * ======================================================
 * LUDO-ENGINE.JS - COMPLETE GAME BOARD & PHYSICS
 * Zupee-Style Ludo Tournament Engine
 * Version: 1.0.0
 * 
 * Renders 15x15 cell matrix board from scratch
 * Implements 2-player competitive mechanics
 * Procedural dice with animation
 * Token movement with capture logic
 * ======================================================
 */

(function() {
    'use strict';

    /**
     * ==============================================
     * LUDO CONFIGURATION CONSTANTS
     * ==============================================
     */
    const LUDO_CONFIG = {
        // Board dimensions
        BOARD_SIZE: 15,          // 15x15 grid
        CELL_SIZE: 40,           // pixels per cell
        TOKEN_RADIUS: 15,
        TOKEN_STROKE_WIDTH: 3,
        HOME_RADIUS: 60,
        SAFE_RADIUS: 22,
        DICE_SIZE: 60,
        
        // Colors
        COLORS: {
            BOARD_BG: '#1a1a2e',
            TRACK: '#2d2d44',
            TRACK_BORDER: '#3d3d5c',
            CELL_HIGHLIGHT: 'rgba(251, 191, 36, 0.15)',
            
            // Player 1 - Red/Gold
            P1_PRIMARY: '#ef4444',
            P1_SECONDARY: '#dc2626',
            P1_GLOW: 'rgba(239, 68, 68, 0.3)',
            P1_HOME: 'rgba(239, 68, 68, 0.15)',
            
            // Player 2 - Blue/Cyan
            P2_PRIMARY: '#3b82f6',
            P2_SECONDARY: '#2563eb',
            P2_GLOW: 'rgba(59, 130, 246, 0.3)',
            P2_HOME: 'rgba(59, 130, 246, 0.15)',
            
            // Neutral
            WHITE: '#f1f5f9',
            GOLD: '#fbbf24',
            GOLD_GLOW: 'rgba(251, 191, 36, 0.2)',
            SAFE: '#10b981',
            SAFE_GLOW: 'rgba(16, 185, 129, 0.2)',
            SHADOW: 'rgba(0, 0, 0, 0.4)',
            DICE_DOT: '#1a1a2e',
        },
        
        // Track positions (0-51 for full circle)
        TRACK_LENGTH: 52,
        TOKENS_PER_PLAYER: 4,
        HOME_ENTRY_POSITION: 51, // Position before entering home
    };

    /**
     * ==============================================
     * TRACK POSITION LOOKUP TABLE
     * Absolute coordinates for each track index (0-51)
     * ==============================================
     */
    const TRACK_POSITIONS = (function() {
        const positions = [];
        const size = LUDO_CONFIG.BOARD_SIZE;
        const cellSize = LUDO_CONFIG.CELL_SIZE;
        const offset = 1; // Board margin
        
        // Define the track path clockwise starting from bottom-left of home
        // The track is a rectangle path around the board
        
        // Bottom row (left to right) - indices 0-5
        for (let col = 6; col >= 1; col--) {
            positions.push({ x: col, y: 13 });
        }
        // Right column (bottom to top) - indices 6-11
        for (let row = 12; row >= 7; row--) {
            positions.push({ x: 13, y: row });
        }
        // Top row (right to left) - indices 12-17
        for (let col = 12; col >= 7; col--) {
            positions.push({ x: col, y: 6 });
        }
        // Left column (top to bottom) - indices 18-23
        for (let row = 7; row <= 12; row++) {
            positions.push({ x: 6, y: row });
        }
        // Bottom row (left to right) - indices 24-29
        for (let col = 5; col >= 0; col--) {
            positions.push({ x: col, y: 13 });
        }
        // Right column (bottom to top) - indices 30-35
        for (let row = 12; row >= 7; row--) {
            positions.push({ x: 14, y: row });
        }
        // Top row (right to left) - indices 36-41
        for (let col = 13; col >= 8; col--) {
            positions.push({ x: col, y: 6 });
        }
        // Left column (top to bottom) - indices 42-47
        for (let row = 7; row <= 12; row++) {
            positions.push({ x: 5, y: row });
        }
        // Bottom row (left to right) - indices 48-51
        for (let col = 6; col <= 13; col++) {
            positions.push({ x: col, y: 14 });
        }
        
        // Convert grid coordinates to pixel coordinates
        return positions.map(pos => ({
            x: pos.x * cellSize + cellSize / 2,
            y: pos.y * cellSize + cellSize / 2
        }));
    })();

    /**
     * ==============================================
     * HOME POSITIONS FOR EACH PLAYER
     * ==============================================
     */
    const HOME_POSITIONS = {
        // Player 1 (Red) - Bottom-Left quadrant
        P1: [
            { x: 2, y: 12 },
            { x: 2, y: 13 },
            { x: 3, y: 12 },
            { x: 3, y: 13 }
        ],
        // Player 2 (Blue) - Top-Right quadrant
        P2: [
            { x: 11, y: 1 },
            { x: 11, y: 2 },
            { x: 12, y: 1 },
            { x: 12, y: 2 }
        ]
    };

    /**
     * ==============================================
     * HOME ENTRY PATHS (Final stretch before home)
     * ==============================================
     */
    const HOME_ENTRY = {
        // Player 1 enters from position 50 (bottom right)
        P1: [
            { x: 13, y: 14 }, // Entry point
            { x: 13, y: 13 },
            { x: 13, y: 12 },
            { x: 13, y: 11 },
            { x: 13, y: 10 },
            { x: 13, y: 9 }  // Home
        ],
        // Player 2 enters from position 24 (top left)
        P2: [
            { x: 1, y: 0 },
            { x: 1, y: 1 },
            { x: 1, y: 2 },
            { x: 1, y: 3 },
            { x: 1, y: 4 },
            { x: 1, y: 5 }  // Home
        ]
    };

    /**
     * ==============================================
     * SAFE CELLS (Star positions where tokens cannot be captured)
     * ==============================================
     */
    const SAFE_CELLS = new Set([
        0, 8, 13, 21, 26, 34, 39, 47
    ]);

    /**
     * ==============================================
     * LUDO ENGINE CLASS
     * ==============================================
     */
    class LudoEngine {
        /**
         * @param {HTMLCanvasElement} canvas - The canvas element
         * @param {Object} options - Configuration options
         */
        constructor(canvas, options = {}) {
            this.canvas = canvas;
            this.ctx = canvas.getContext('2d');
            
            // Board dimensions
            this.cellSize = options.cellSize || LUDO_CONFIG.CELL_SIZE;
            this.boardSize = LUDO_CONFIG.BOARD_SIZE;
            this.tokenRadius = options.tokenRadius || LUDO_CONFIG.TOKEN_RADIUS;
            
            // Calculate canvas size
            this.canvasWidth = this.boardSize * this.cellSize;
            this.canvasHeight = this.boardSize * this.cellSize;
            this.canvas.width = this.canvasWidth;
            this.canvas.height = this.canvasHeight;
            
            // Game state
            this.gameState = {
                status: 'waiting', // 'waiting', 'playing', 'rolling', 'moving', 'completed'
                currentTurn: 1, // 1 or 2
                diceValue: 0,
                diceRolling: false,
                selectedToken: null,
                availableMoves: [],
                moveHistory: [],
                turnNumber: 0,
                maxTurns: 100,
                winner: null,
                isGameOver: false,
                player1Ready: false,
                player2Ready: false,
                canRoll: true,
                hasRolled: false,
                forcedCapture: false,
            };
            
            // Player data
            this.players = {
                1: {
                    id: 1,
                    name: options.player1Name || 'Player 1',
                    color: LUDO_CONFIG.COLORS.P1_PRIMARY,
                    secondaryColor: LUDO_CONFIG.COLORS.P1_SECONDARY,
                    homeColor: LUDO_CONFIG.COLORS.P1_HOME,
                    tokens: [],
                    homeCount: 0,
                    finished: false,
                    isHuman: true,
                },
                2: {
                    id: 2,
                    name: options.player2Name || 'Player 2',
                    color: LUDO_CONFIG.COLORS.P2_PRIMARY,
                    secondaryColor: LUDO_CONFIG.COLORS.P2_SECONDARY,
                    homeColor: LUDO_CONFIG.COLORS.P2_HOME,
                    tokens: [],
                    homeCount: 0,
                    finished: false,
                    isHuman: options.player2Human !== undefined ? options.player2Human : true,
                }
            };
            
            // Initialize tokens
            this.initializeTokens();
            
            // Animation frame ID
            this.animationId = null;
            
            // Dice animation state
            this.diceAnimation = {
                active: false,
                currentValue: 1,
                elapsed: 0,
                duration: 800, // ms
                interval: 50,
                callback: null,
            };
            
            // Mouse/touch interaction
            this.interaction = {
                active: true,
                hoveredToken: null,
                selectedCell: null,
            };
            
            // Event callbacks
            this.callbacks = {
                onMove: null,
                onCapture: null,
                onWin: null,
                onLose: null,
                onTurnChange: null,
                onDiceRoll: null,
                onTokenSelect: null,
                onGameStateChange: null,
            };
            
            // Bind events
            this.bindEvents();
            
            // Initial render
            this.render();
            
            console.log('LudoEngine: Initialized');
        }

        /**
         * ==============================================
         * TOKEN INITIALIZATION
         * ==============================================
         */
        initializeTokens() {
            // Player 1 tokens (Red) - Start at home positions
            const p1Home = HOME_POSITIONS.P1;
            this.players[1].tokens = p1Home.map((pos, index) => ({
                id: index + 1,
                playerId: 1,
                position: -1, // -1 = at home
                homePosition: index,
                isHome: true,
                isFinished: false,
                trackIndex: -1,
                x: pos.x * this.cellSize + this.cellSize / 2,
                y: pos.y * this.cellSize + this.cellSize / 2,
                homeX: pos.x * this.cellSize + this.cellSize / 2,
                homeY: pos.y * this.cellSize + this.cellSize / 2,
                isActive: false,
                hasEntered: false,
                homeEntryProgress: 0,
            }));
            
            // Player 2 tokens (Blue) - Start at home positions
            const p2Home = HOME_POSITIONS.P2;
            this.players[2].tokens = p2Home.map((pos, index) => ({
                id: index + 1,
                playerId: 2,
                position: -1,
                homePosition: index,
                isHome: true,
                isFinished: false,
                trackIndex: -1,
                x: pos.x * this.cellSize + this.cellSize / 2,
                y: pos.y * this.cellSize + this.cellSize / 2,
                homeX: pos.x * this.cellSize + this.cellSize / 2,
                homeY: pos.y * this.cellSize + this.cellSize / 2,
                isActive: false,
                hasEntered: false,
                homeEntryProgress: 0,
            }));
        }

        /**
         * ==============================================
         * CANVAS RENDERING
         * ==============================================
         */
        render() {
            const ctx = this.ctx;
            const w = this.canvasWidth;
            const h = this.canvasHeight;
            
            // Clear canvas
            ctx.clearRect(0, 0, w, h);
            
            // Draw board background
            this.drawBoardBackground(ctx);
            
            // Draw grid and track
            this.drawGrid(ctx);
            
            // Draw home zones
            this.drawHomeZones(ctx);
            
            // Draw safe cells
            this.drawSafeCells(ctx);
            
            // Draw home entry paths
            this.drawHomeEntries(ctx);
            
            // Draw tokens
            this.drawTokens(ctx);
            
            // Draw dice
            this.drawDice(ctx);
            
            // Draw turn indicator
            this.drawTurnIndicator(ctx);
            
            // Draw game status overlay
            if (this.gameState.isGameOver) {
                this.drawGameOver(ctx);
            }
        }

        /**
         * ==============================================
         * DRAW BOARD BACKGROUND
         * ==============================================
         */
        drawBoardBackground(ctx) {
            const w = this.canvasWidth;
            const h = this.canvasHeight;
            
            // Main background
            const gradient = ctx.createRadialGradient(w/2, h/2, 0, w/2, h/2, w);
            gradient.addColorStop(0, '#1e293b');
            gradient.addColorStop(1, '#0a0e1a');
            ctx.fillStyle = gradient;
            ctx.fillRect(0, 0, w, h);
            
            // Board border glow
            ctx.shadowColor = 'rgba(251, 191, 36, 0.05)';
            ctx.shadowBlur = 30;
            ctx.strokeStyle = 'rgba(251, 191, 36, 0.1)';
            ctx.lineWidth = 2;
            ctx.strokeRect(2, 2, w - 4, h - 4);
            ctx.shadowBlur = 0;
        }

        /**
         * ==============================================
         * DRAW GRID AND TRACK
         * ==============================================
         */
        drawGrid(ctx) {
            const size = this.boardSize;
            const cellSize = this.cellSize;
            
            // Define track area (cells that are part of the track)
            const trackCells = new Set();
            
            // Bottom track (rows 13-14)
            for (let col = 0; col < size; col++) {
                trackCells.add(`${col},13`);
                trackCells.add(`${col},14`);
            }
            // Top track (rows 0-1)
            for (let col = 0; col < size; col++) {
                trackCells.add(`${col},0`);
                trackCells.add(`${col},1`);
            }
            // Left track (cols 0-1)
            for (let row = 0; row < size; row++) {
                trackCells.add(`0,${row}`);
                trackCells.add(`1,${row}`);
            }
            // Right track (cols 13-14)
            for (let row = 0; row < size; row++) {
                trackCells.add(`13,${row}`);
                trackCells.add(`14,${row}`);
            }
            
            // Draw each cell
            for (let row = 0; row < size; row++) {
                for (let col = 0; col < size; col++) {
                    const x = col * cellSize;
                    const y = row * cellSize;
                    const isTrack = trackCells.has(`${col},${row}`);
                    const isHome = this.isHomeCell(col, row);
                    
                    if (isHome) {
                        // Home zone cells - draw as colored areas
                        const color = this.getHomeCellColor(col, row);
                        ctx.fillStyle = color;
                        ctx.fillRect(x, y, cellSize, cellSize);
                    } else if (isTrack) {
                        // Track cells
                        ctx.fillStyle = LUDO_CONFIG.COLORS.TRACK;
                        ctx.fillRect(x, y, cellSize, cellSize);
                        ctx.strokeStyle = LUDO_CONFIG.COLORS.TRACK_BORDER;
                        ctx.lineWidth = 0.5;
                        ctx.strokeRect(x, y, cellSize, cellSize);
                    } else {
                        // Empty cells (center area)
                        ctx.fillStyle = 'rgba(20, 20, 40, 0.5)';
                        ctx.fillRect(x, y, cellSize, cellSize);
                    }
                }
            }
            
            // Draw track path highlights
            this.drawTrackPath(ctx);
        }

        /**
         * ==============================================
         * CHECK IF CELL IS IN HOME ZONE
         * ==============================================
         */
        isHomeCell(col, row) {
            // Player 1 home (bottom-left)
            if (col >= 0 && col <= 5 && row >= 9 && row <= 14) return true;
            // Player 2 home (top-right)
            if (col >= 9 && col <= 14 && row >= 0 && row <= 5) return true;
            // Center home area (cross)
            if (col >= 6 && col <= 8 && row >= 6 && row <= 8) return true;
            return false;
        }

        /**
         * ==============================================
         * GET HOME CELL COLOR
         * ==============================================
         */
        getHomeCellColor(col, row) {
            // Player 1 home (bottom-left)
            if (col >= 0 && col <= 5 && row >= 9 && row <= 14) {
                return LUDO_CONFIG.COLORS.P1_HOME;
            }
            // Player 2 home (top-right)
            if (col >= 9 && col <= 14 && row >= 0 && row <= 5) {
                return LUDO_CONFIG.COLORS.P2_HOME;
            }
            // Center area
            return 'rgba(30, 30, 60, 0.3)';
        }

        /**
         * ==============================================
         * DRAW TRACK PATH
         * ==============================================
         */
        drawTrackPath(ctx) {
            const cellSize = this.cellSize;
            const positions = TRACK_POSITIONS;
            
            // Draw connecting lines between track positions
            ctx.strokeStyle = 'rgba(251, 191, 36, 0.08)';
            ctx.lineWidth = 2;
            ctx.setLineDash([4, 8]);
            
            for (let i = 0; i < positions.length; i++) {
                const current = positions[i];
                const next = positions[(i + 1) % positions.length];
                
                ctx.beginPath();
                ctx.moveTo(current.x, current.y);
                ctx.lineTo(next.x, next.y);
                ctx.stroke();
            }
            ctx.setLineDash([]);
        }

        /**
         * ==============================================
         * DRAW HOME ZONES
         * ==============================================
         */
        drawHomeZones(ctx) {
            const cellSize = this.cellSize;
            
            // Player 1 home zone (bottom-left)
            this.drawHomeZone(ctx, 1, 9, 6, 6, LUDO_CONFIG.COLORS.P1_PRIMARY, LUDO_CONFIG.COLORS.P1_HOME);
            
            // Player 2 home zone (top-right)
            this.drawHomeZone(ctx, 9, 0, 6, 6, LUDO_CONFIG.COLORS.P2_PRIMARY, LUDO_CONFIG.COLORS.P2_HOME);
        }

        /**
         * ==============================================
         * DRAW SINGLE HOME ZONE
         * ==============================================
         */
        drawHomeZone(ctx, startCol, startRow, cols, rows, color, bgColor) {
            const cellSize = this.cellSize;
            const x = startCol * cellSize;
            const y = startRow * cellSize;
            const w = cols * cellSize;
            const h = rows * cellSize;
            
            // Background
            ctx.fillStyle = bgColor;
            ctx.fillRect(x, y, w, h);
            
            // Border
            ctx.strokeStyle = color;
            ctx.lineWidth = 2;
            ctx.globalAlpha = 0.3;
            ctx.strokeRect(x, y, w, h);
            ctx.globalAlpha = 1;
            
            // Inner decoration - concentric circles
            const centerX = x + w / 2;
            const centerY = y + h / 2;
            const radius = Math.min(w, h) / 3;
            
            for (let i = 0; i < 3; i++) {
                ctx.beginPath();
                ctx.arc(centerX, centerY, radius * (i + 1) / 3, 0, Math.PI * 2);
                ctx.strokeStyle = color;
                ctx.globalAlpha = 0.15;
                ctx.lineWidth = 1;
                ctx.stroke();
            }
            ctx.globalAlpha = 1;
            
            // Player label
            ctx.fillStyle = color;
            ctx.globalAlpha = 0.3;
            ctx.font = 'bold 24px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            const label = startRow < 5 ? 'P2' : 'P1';
            ctx.fillText(label, centerX, centerY);
            ctx.globalAlpha = 1;
        }

        /**
         * ==============================================
         * DRAW SAFE CELLS (Star/Shield icons)
         * ==============================================
         */
        drawSafeCells(ctx) {
            const cellSize = this.cellSize;
            
            SAFE_CELLS.forEach(index => {
                if (index < TRACK_POSITIONS.length) {
                    const pos = TRACK_POSITIONS[index];
                    const x = pos.x - cellSize / 2;
                    const y = pos.y - cellSize / 2;
                    
                    // Glow effect
                    const gradient = ctx.createRadialGradient(
                        pos.x, pos.y, 0,
                        pos.x, pos.y, cellSize / 2
                    );
                    gradient.addColorStop(0, LUDO_CONFIG.COLORS.SAFE_GLOW);
                    gradient.addColorStop(1, 'transparent');
                    ctx.fillStyle = gradient;
                    ctx.fillRect(x, y, cellSize, cellSize);
                    
                    // Star shape
                    this.drawStar(ctx, pos.x, pos.y, 5, 8, 4);
                }
            });
        }

        /**
         * ==============================================
         * DRAW STAR (For safe cells)
         * ==============================================
         */
        drawStar(ctx, cx, cy, spikes, outerRadius, innerRadius) {
            ctx.save();
            ctx.translate(cx, cy);
            
            ctx.beginPath();
            for (let i = 0; i < spikes * 2; i++) {
                const radius = i % 2 === 0 ? outerRadius : innerRadius;
                const angle = (i / (spikes * 2)) * Math.PI * 2 - Math.PI / 2;
                const x = Math.cos(angle) * radius;
                const y = Math.sin(angle) * radius;
                if (i === 0) ctx.moveTo(x, y);
                else ctx.lineTo(x, y);
            }
            ctx.closePath();
            
            ctx.fillStyle = LUDO_CONFIG.COLORS.SAFE;
            ctx.globalAlpha = 0.4;
            ctx.fill();
            ctx.strokeStyle = LUDO_CONFIG.COLORS.SAFE;
            ctx.globalAlpha = 0.6;
            ctx.lineWidth = 1;
            ctx.stroke();
            ctx.globalAlpha = 1;
            
            ctx.restore();
        }

        /**
         * ==============================================
         * DRAW HOME ENTRIES
         * ==============================================
         */
        drawHomeEntries(ctx) {
            const cellSize = this.cellSize;
            
            // Player 1 home entry (bottom-right path)
            this.drawHomeEntryPath(ctx, HOME_ENTRY.P1, LUDO_CONFIG.COLORS.P1_PRIMARY);
            
            // Player 2 home entry (top-left path)
            this.drawHomeEntryPath(ctx, HOME_ENTRY.P2, LUDO_CONFIG.COLORS.P2_PRIMARY);
        }

        /**
         * ==============================================
         * DRAW SINGLE HOME ENTRY PATH
         * ==============================================
         */
        drawHomeEntryPath(ctx, path, color) {
            const cellSize = this.cellSize;
            
            path.forEach((pos, index) => {
                const x = pos.x * cellSize;
                const y = pos.y * cellSize;
                
                // Cell background
                ctx.fillStyle = color;
                ctx.globalAlpha = 0.08;
                ctx.fillRect(x, y, cellSize, cellSize);
                ctx.globalAlpha = 1;
                
                // Border
                ctx.strokeStyle = color;
                ctx.globalAlpha = 0.2;
                ctx.lineWidth = 1;
                ctx.strokeRect(x, y, cellSize, cellSize);
                ctx.globalAlpha = 1;
                
                // Small indicator arrow
                if (index < path.length - 1) {
                    ctx.fillStyle = color;
                    ctx.globalAlpha = 0.3;
                    ctx.font = '12px Inter, sans-serif';
                    ctx.textAlign = 'center';
                    ctx.textBaseline = 'middle';
                    ctx.fillText('↓', x + cellSize/2, y + cellSize/2);
                    ctx.globalAlpha = 1;
                }
            });
        }

        /**
         * ==============================================
         * DRAW TOKENS
         * ==============================================
         */
        drawTokens(ctx) {
            // Draw all tokens for both players
            this.players[1].tokens.forEach(token => this.drawToken(ctx, token));
            this.players[2].tokens.forEach(token => this.drawToken(ctx, token));
            
            // Highlight selected token
            if (this.gameState.selectedToken) {
                const token = this.gameState.selectedToken;
                ctx.save();
                ctx.shadowColor = LUDO_CONFIG.COLORS.GOLD_GLOW;
                ctx.shadowBlur = 30;
                ctx.strokeStyle = LUDO_CONFIG.COLORS.GOLD;
                ctx.lineWidth = 3;
                ctx.setLineDash([4, 4]);
                ctx.beginPath();
                ctx.arc(token.x, token.y, this.tokenRadius + 6, 0, Math.PI * 2);
                ctx.stroke();
                ctx.setLineDash([]);
                ctx.restore();
            }
        }

        /**
         * ==============================================
         * DRAW SINGLE TOKEN
         * ==============================================
         */
        drawToken(ctx, token) {
            const player = this.players[token.playerId];
            const radius = this.tokenRadius;
            const x = token.x;
            const y = token.y;
            
            // Skip if token is finished (in home)
            if (token.isFinished) return;
            
            ctx.save();
            
            // Shadow
            ctx.shadowColor = LUDO_CONFIG.COLORS.SHADOW;
            ctx.shadowBlur = 10;
            ctx.shadowOffsetX = 2;
            ctx.shadowOffsetY = 2;
            
            // Glow effect
            const glowRadius = radius + 10;
            const gradient = ctx.createRadialGradient(x, y, 0, x, y, glowRadius);
            gradient.addColorStop(0, player.color + '30');
            gradient.addColorStop(1, 'transparent');
            ctx.fillStyle = gradient;
            ctx.beginPath();
            ctx.arc(x, y, glowRadius, 0, Math.PI * 2);
            ctx.fill();
            
            // Main body
            const grad = ctx.createRadialGradient(
                x - radius/3, y - radius/3, 0,
                x, y, radius
            );
            grad.addColorStop(0, '#ffffff');
            grad.addColorStop(0.3, player.color);
            grad.addColorStop(1, player.secondaryColor);
            
            ctx.shadowBlur = 5;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.fillStyle = grad;
            ctx.fill();
            
            // Stroke ring
            ctx.shadowBlur = 0;
            ctx.strokeStyle = LUDO_CONFIG.COLORS.GOLD;
            ctx.lineWidth = 2.5;
            ctx.globalAlpha = 0.6;
            ctx.beginPath();
            ctx.arc(x, y, radius, 0, Math.PI * 2);
            ctx.stroke();
            ctx.globalAlpha = 1;
            
            // Inner highlight
            ctx.shadowBlur = 0;
            ctx.beginPath();
            ctx.arc(x - radius/4, y - radius/4, radius/3, 0, Math.PI * 2);
            ctx.fillStyle = 'rgba(255, 255, 255, 0.3)';
            ctx.fill();
            
            // Token number
            ctx.fillStyle = 'rgba(255, 255, 255, 0.9)';
            ctx.font = 'bold 12px Inter, sans-serif';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
            ctx.shadowBlur = 4;
            ctx.fillText(token.id, x, y + 1);
            
            ctx.restore();
        }

        /**
         * ==============================================
         * DRAW DICE
         * ==============================================
         */
        drawDice(ctx) {
            const diceSize = LUDO_CONFIG.DICE_SIZE;
            const x = this.canvasWidth - diceSize - 20;
            const y = 20;
            const value = this.gameState.diceValue || 1;
            
            ctx.save();
            
            // Dice shadow
            ctx.shadowColor = LUDO_CONFIG.COLORS.SHADOW;
            ctx.shadowBlur = 20;
            ctx.shadowOffsetX = 4;
            ctx.shadowOffsetY = 4;
            
            // Dice body
            const gradient = ctx.createRadialGradient(
                x + 10, y + 10, 0,
                x + diceSize/2, y + diceSize/2, diceSize/2
            );
            gradient.addColorStop(0, '#f8fafc');
            gradient.addColorStop(0.7, '#e2e8f0');
            gradient.addColorStop(1, '#cbd5e1');
            
            ctx.shadowBlur = 15;
            ctx.beginPath();
            ctx.roundRect(x, y, diceSize, diceSize, 10);
            ctx.fillStyle = gradient;
            ctx.fill();
            
            // Dice border
            ctx.shadowBlur = 0;
            ctx.strokeStyle = 'rgba(0, 0, 0, 0.1)';
            ctx.lineWidth = 1;
            ctx.beginPath();
            ctx.roundRect(x, y, diceSize, diceSize, 10);
            ctx.stroke();
            
            // Dice dots
            const dotPositions = this.getDiceDotPositions(value, diceSize);
            const dotRadius = diceSize * 0.07;
            
            dotPositions.forEach(pos => {
                ctx.shadowColor = 'rgba(0, 0, 0, 0.2)';
                ctx.shadowBlur = 4;
                ctx.beginPath();
                ctx.arc(x + pos.x, y + pos.y, dotRadius, 0, Math.PI * 2);
                ctx.fillStyle = LUDO_CONFIG.COLORS.DICE_DOT;
                ctx.fill();
            });
            
            // Click hint
            if (this.gameState.canRoll && !this.gameState.hasRolled && this.gameState.status === 'playing') {
                ctx.shadowBlur = 0;
                ctx.fillStyle = 'rgba(251, 191, 36, 0.6)';
                ctx.font = '10px Inter, sans-serif';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'top';
                ctx.fillText('CLICK TO ROLL', x + diceSize/2, y + diceSize + 8);
            }
            
            ctx.restore();
        }

        /**
         * ==============================================
         * GET DICE DOT POSITIONS
         * ==============================================
         */
        getDiceDotPositions(value, size) {
            const margin = size * 0.25;
            const center = size / 2;
            
            const positions = {
                1: [{ x: center, y: center }],
                2: [{ x: margin, y: margin }, { x: size - margin, y: size - margin }],
                3: [{ x: margin, y: margin }, { x: center, y: center }, { x: size - margin, y: size - margin }],
                4: [{ x: margin, y: margin }, { x: size - margin, y: margin }, { x: margin, y: size - margin }, { x: size - margin, y: size - margin }],
                5: [{ x: margin, y: margin }, { x: size - margin, y: margin }, { x: center, y: center }, { x: margin, y: size - margin }, { x: size - margin, y: size - margin }],
                6: [{ x: margin, y: margin }, { x: size - margin, y: margin }, { x: margin, y: center }, { x: size - margin, y: center }, { x: margin, y: size - margin }, { x: size - margin, y: size - margin }],
            };
            
            return positions[value] || positions[1];
        }

        /**
         * ==============================================
         * DRAW TURN INDICATOR
         * ==============================================
         */
        drawTurnIndicator(ctx) {
            const currentPlayer = this.players[this.gameState.currentTurn];
            if (!currentPlayer) return;
            
            const x = 20;
            const y = 20;
            const size = 40;
            
            ctx.save();
            
            // Background pill
            ctx.shadowBlur = 0;
            ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
            ctx.beginPath();
            ctx.roundRect(x, y, size + 80, size, 20);
            ctx.fill();
            
            // Player color indicator
            ctx.shadowBlur = 10;
            ctx.shadowColor = currentPlayer.color + '40';
            ctx.fillStyle = currentPlayer.color;
            ctx.beginPath();
            ctx.arc(x + 20, y + size/2, 14, 0, Math.PI * 2);
            ctx.fill();
            
            // Turn text
            ctx.shadowBlur = 0;
            ctx.fillStyle = '#ffffff';
            ctx.font = 'bold 12px Inter, sans-serif';
            ctx.textAlign = 'left';
            ctx.textBaseline = 'middle';
            ctx.fillText(`${currentPlayer.name}'s Turn`, x + 36, y + size/2);
            
            ctx.restore();
        }

        /**
         * ==============================================
         * DRAW GAME OVER OVERLAY
         * ==============================================
         */
        drawGameOver(ctx) {
            const w = this.canvasWidth;
            const h = this.canvasHeight;
            
            ctx.save();
            
            // Dim overlay
            ctx.fillStyle = 'rgba(0, 0, 0, 0.6)';
            ctx.fillRect(0, 0, w, h);
            
            // Winner card
            const winner = this.gameState.winner;
            const player = winner ? this.players[winner] : null;
            const isWin = winner === 1;
            
            const cardWidth = 280;
            const cardHeight = 180;
            const cardX = (w - cardWidth) / 2;
            const cardY = (h - cardHeight) / 2;
            
            // Card background
            ctx.shadowBlur = 40;
            ctx.shadowColor = 'rgba(0, 0, 0, 0.5)';
            ctx.fillStyle = '#1a1a2e';
            ctx.beginPath();
            ctx.roundRect(cardX, cardY, cardWidth, cardHeight, 16);
            ctx.fill();
            
            ctx.shadowBlur = 0;
            ctx.strokeStyle = player ? player.color : '#ffffff';
            ctx.lineWidth = 2;
            ctx.globalAlpha = 0.3;
            ctx.beginPath();
            ctx.roundRect(cardX, cardY, cardWidth, cardHeight, 16);
            ctx.stroke();
            ctx.globalAlpha = 1;
            
            // Result text
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            
            if (winner) {
                // Win/Lose icon
                ctx.font = '48px Inter, sans-serif';
                ctx.fillText(isWin ? '🏆' : '😢', w/2, cardY + 50);
                
                ctx.font = 'bold 22px Inter, sans-serif';
                ctx.fillStyle = isWin ? LUDO_CONFIG.COLORS.GOLD : '#ef4444';
                ctx.fillText(isWin ? 'YOU WIN!' : 'YOU LOSE', w/2, cardY + 100);
                
                ctx.font = '14px Inter, sans-serif';
                ctx.fillStyle = '#94a3b8';
                ctx.fillText(`${player.name} finished first!`, w/2, cardY + 130);
            } else {
                ctx.font = '24px Inter, sans-serif';
                ctx.fillStyle = '#94a3b8';
                ctx.fillText('Game Over', w/2, cardY + 80);
            }
            
            ctx.restore();
        }

        /**
         * ==============================================
         * TOKEN POSITION MANAGEMENT
         * ==============================================
         */
        getTokenPosition(playerId, tokenId) {
            const player = this.players[playerId];
            if (!player) return null;
            return player.tokens.find(t => t.id === tokenId) || null;
        }

        getTokenByTrackIndex(playerId, trackIndex) {
            const player = this.players[playerId];
            if (!player) return null;
            return player.tokens.find(t => t.trackIndex === trackIndex) || null;
        }

        getTokenAtTrackPosition(trackIndex) {
            // Check both players for a token at this track position
            for (let pId of [1, 2]) {
                const token = this.getTokenByTrackIndex(pId, trackIndex);
                if (token && !token.isFinished && !token.isHome) {
                    return token;
                }
            }
            return null;
        }

        /**
         * ==============================================
         * MOVE VALIDATION
         * ==============================================
         */
        getValidMoves(playerId, tokenId, diceValue) {
            const player = this.players[playerId];
            if (!player) return [];
            
            const token = this.getTokenPosition(playerId, tokenId);
            if (!token || token.isFinished) return [];
            
            const moves = [];
            const currentTrackIndex = token.trackIndex;
            
            // Check if token is at home
            if (token.isHome) {
                // Can only leave home with a 6 or 1 (depending on rules)
                if (diceValue === 6 || diceValue === 1) {
                    // Enter track at position 0 for P1, position 26 for P2
                    const startIndex = playerId === 1 ? 0 : 26;
                    const targetToken = this.getTokenAtTrackPosition(startIndex);
                    
                    // Can't move if occupied by own token
                    if (targetToken && targetToken.playerId === playerId) {
                        return moves;
                    }
                    
                    moves.push({
                        tokenId: token.id,
                        fromTrackIndex: -1,
                        toTrackIndex: startIndex,
                        isHomeExit: true,
                        isCapture: targetToken && targetToken.playerId !== playerId,
                        targetToken: targetToken,
                    });
                }
                return moves;
            }
            
            // Token is on track
            let newIndex = (currentTrackIndex + diceValue) % LUDO_CONFIG.TRACK_LENGTH;
            let homeEntryProgress = -1;
            
            // Check if entering home stretch
            const entryStart = playerId === 1 ? 50 : 24;
            const entryEnd = playerId === 1 ? 55 : 29; // Virtual positions for home
            
            // Simple home entry check
            if (currentTrackIndex <= entryStart && currentTrackIndex + diceValue > entryStart) {
                // Entering home stretch
                const progress = (currentTrackIndex + diceValue) - entryStart;
                if (progress <= 5) {
                    // Valid home entry
                    newIndex = entryStart + progress;
                    moves.push({
                        tokenId: token.id,
                        fromTrackIndex: currentTrackIndex,
                        toTrackIndex: newIndex,
                        isHomeEntry: true,
                        homeProgress: progress,
                        isCapture: false,
                        targetToken: null,
                    });
                    return moves;
                }
            }
            
            // Check if target position is occupied
            const targetToken = this.getTokenAtTrackPosition(newIndex);
            
            // Can't move to own token position
            if (targetToken && targetToken.playerId === playerId) {
                return moves;
            }
            
            // Can't move to safe cell if opponent is there (safe cells are safe)
            if (SAFE_CELLS.has(newIndex) && targetToken && targetToken.playerId !== playerId) {
                return moves;
            }
            
            moves.push({
                tokenId: token.id,
                fromTrackIndex: currentTrackIndex,
                toTrackIndex: newIndex,
                isHomeExit: false,
                isHomeEntry: false,
                isCapture: targetToken && targetToken.playerId !== playerId,
                targetToken: targetToken,
                homeProgress: -1,
            });
            
            return moves;
        }

        /**
         * ==============================================
         * EXECUTE MOVE
         * ==============================================
         */
        executeMove(move) {
            const playerId = this.gameState.currentTurn;
            const player = this.players[playerId];
            const token = this.getTokenPosition(playerId, move.tokenId);
            
            if (!token) return false;
            
            // Store previous state for undo
            const prevState = {
                token: { ...token },
                gameState: { ...this.gameState },
            };
            
            // Execute the move
            if (move.isHomeExit) {
                // Move token from home to track
                token.isHome = false;
                token.hasEntered = true;
                token.isActive = true;
                token.trackIndex = move.toTrackIndex;
                const pos = TRACK_POSITIONS[move.toTrackIndex];
                token.x = pos.x;
                token.y = pos.y;
            } else if (move.isHomeEntry) {
                // Enter home stretch
                token.trackIndex = move.toTrackIndex;
                const pos = TRACK_POSITIONS[move.toTrackIndex];
                token.x = pos.x;
                token.y = pos.y;
                token.homeEntryProgress = move.homeProgress || 0;
            } else {
                // Normal move on track
                token.trackIndex = move.toTrackIndex;
                const pos = TRACK_POSITIONS[move.toTrackIndex];
                token.x = pos.x;
                token.y = pos.y;
            }
            
            // Handle capture
            if (move.isCapture && move.targetToken) {
                this.captureToken(move.targetToken);
            }
            
            // Check if token reached home
            if (move.isHomeEntry && move.homeProgress === 5) {
                token.isFinished = true;
                token.isActive = false;
                player.homeCount++;
                
                // Check for win
                if (player.homeCount === 4) {
                    this.gameState.winner = playerId;
                    this.gameState.isGameOver = true;
                    this.gameState.status = 'completed';
                    
                    if (this.callbacks.onWin) {
                        this.callbacks.onWin(playerId);
                    }
                    
                    if (typeof LudoAudioEngine !== 'undefined') {
                        LudoAudioEngine.playWin();
                    }
                }
            }
            
            // Trigger audio
            if (move.isCapture) {
                if (typeof LudoAudioEngine !== 'undefined') {
                    LudoAudioEngine.playCapture();
                }
                if (this.callbacks.onCapture) {
                    this.callbacks.onCapture(move.targetToken);
                }
            } else {
                if (typeof LudoAudioEngine !== 'undefined') {
                    LudoAudioEngine.playTokenMove();
                }
            }
            
            // Update move history
            this.gameState.moveHistory.push({
                playerId: playerId,
                tokenId: token.id,
                move: move,
                timestamp: Date.now(),
            });
            
            this.gameState.turnNumber++;
            
            // Check for max turns
            if (this.gameState.turnNumber >= this.gameState.maxTurns) {
                this.gameState.isGameOver = true;
                this.gameState.status = 'completed';
                // Determine winner by most home tokens
                const p1Home = this.players[1].homeCount;
                const p2Home = this.players[2].homeCount;
                if (p1Home > p2Home) {
                    this.gameState.winner = 1;
                } else if (p2Home > p1Home) {
                    this.gameState.winner = 2;
                }
            }
            
            if (this.callbacks.onMove) {
                this.callbacks.onMove(move);
            }
            
            this.render();
            return true;
        }

        /**
         * ==============================================
         * CAPTURE TOKEN
         * ==============================================
         */
        captureToken(token) {
            // Return token to home
            const player = this.players[token.playerId];
            const homePos = HOME_POSITIONS[token.playerId === 1 ? 'P1' : 'P2'];
            const homeIndex = token.id - 1;
            
            token.isHome = true;
            token.isActive = false;
            token.hasEntered = false;
            token.trackIndex = -1;
            token.homeEntryProgress = 0;
            
            if (homeIndex < homePos.length) {
                token.x = homePos[homeIndex].x * this.cellSize + this.cellSize / 2;
                token.y = homePos[homeIndex].y * this.cellSize + this.cellSize / 2;
            }
            
            // Audio feedback
            if (typeof LudoAudioEngine !== 'undefined') {
                LudoAudioEngine.playError();
            }
        }

        /**
         * ==============================================
         * DICE ROLLING
         * ==============================================
         */
        rollDice() {
            if (this.gameState.diceRolling) return;
            if (this.gameState.isGameOver) return;
            if (!this.gameState.canRoll) return;
            if (this.gameState.hasRolled) return;
            
            this.gameState.diceRolling = true;
            this.gameState.canRoll = false;
            
            // Audio: Dice roll
            if (typeof LudoAudioEngine !== 'undefined') {
                LudoAudioEngine.playDiceRoll();
            }
            
            // Animate dice
            this.animateDice(() => {
                // Get server-validated value (for now, random)
                const value = Math.floor(Math.random() * 6) + 1;
                this.gameState.diceValue = value;
                this.gameState.hasRolled = true;
                this.gameState.diceRolling = false;
                
                // Check for 6 (extra turn)
                if (value === 6) {
                    // Award extra turn
                    setTimeout(() => {
                        this.gameState.hasRolled = false;
                        this.gameState.canRoll = true;
                        if (this.callbacks.onDiceRoll) {
                            this.callbacks.onDiceRoll(value, true);
                        }
                    }, 500);
                }
                
                if (this.callbacks.onDiceRoll) {
                    this.callbacks.onDiceRoll(value, false);
                }
                
                // Check for forced moves
                const moves = this.getAvailableMoves(value);
                if (moves.length === 0) {
                    // No moves available, end turn
                    setTimeout(() => {
                        this.endTurn();
                    }, 300);
                }
                
                this.render();
            });
        }

        /**
         * ==============================================
         * ANIMATE DICE
         * ==============================================
         */
        animateDice(callback) {
            const startTime = Date.now();
            const duration = 800;
            let count = 0;
            
            const animate = () => {
                const elapsed = Date.now() - startTime;
                const progress = elapsed / duration;
                
                if (progress < 1) {
                    // Random dice value for animation
                    this.gameState.diceValue = Math.floor(Math.random() * 6) + 1;
                    this.render();
                    requestAnimationFrame(animate);
                } else {
                    // Final value will be set in callback
                    if (callback) callback();
                }
            };
            
            animate();
        }

        /**
         * ==============================================
         * GET AVAILABLE MOVES FOR CURRENT TURN
         * ==============================================
         */
        getAvailableMoves(diceValue) {
            const playerId = this.gameState.currentTurn;
            const player = this.players[playerId];
            const allMoves = [];
            
            player.tokens.forEach(token => {
                const moves = this.getValidMoves(playerId, token.id, diceValue);
                moves.forEach(move => {
                    allMoves.push(move);
                });
            });
            
            return allMoves;
        }

        /**
         * ==============================================
         * END TURN
         * ==============================================
         */
        endTurn() {
            if (this.gameState.isGameOver) return;
            
            this.gameState.hasRolled = false;
            this.gameState.canRoll = true;
            this.gameState.selectedToken = null;
            
            // Switch turns
            this.gameState.currentTurn = this.gameState.currentTurn === 1 ? 2 : 1;
            
            if (this.callbacks.onTurnChange) {
                this.callbacks.onTurnChange(this.gameState.currentTurn);
            }
            
            // AI turn if applicable
            const currentPlayer = this.players[this.gameState.currentTurn];
            if (currentPlayer && !currentPlayer.isHuman) {
                this.aiTurn();
            }
            
            this.render();
        }

        /**
         * ==============================================
         * AI TURN (Simple Bot)
         * ==============================================
         */
        aiTurn() {
            if (this.gameState.isGameOver) return;
            
            setTimeout(() => {
                // AI logic
                this.rollDice();
                
                // After dice roll, wait and then make a move
                setTimeout(() => {
                    if (this.gameState.hasRolled && !this.gameState.isGameOver) {
                        const moves = this.getAvailableMoves(this.gameState.diceValue);
                        if (moves.length > 0) {
                            // Choose first available move
                            const move = moves[0];
                            this.executeMove(move);
                            
                            // If got 6, roll again
                            if (this.gameState.diceValue === 6) {
                                setTimeout(() => {
                                    this.aiTurn();
                                }, 500);
                            } else {
                                setTimeout(() => {
                                    this.endTurn();
                                }, 500);
                            }
                        } else {
                            setTimeout(() => {
                                this.endTurn();
                            }, 500);
                        }
                    }
                }, 800);
            }, 500);
        }

        /**
         * ==============================================
         * EVENT HANDLING
         * ==============================================
         */
        bindEvents() {
            // Mouse events
            this.canvas.addEventListener('click', (e) => {
                const rect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;
                const x = (e.clientX - rect.left) * scaleX;
                const y = (e.clientY - rect.top) * scaleY;
                
                this.handleClick(x, y);
            });
            
            // Touch events
            this.canvas.addEventListener('touchstart', (e) => {
                e.preventDefault();
                const rect = this.canvas.getBoundingClientRect();
                const touch = e.touches[0];
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;
                const x = (touch.clientX - rect.left) * scaleX;
                const y = (touch.clientY - rect.top) * scaleY;
                
                this.handleClick(x, y);
            }, { passive: false });
            
            // Mouse move for hover effects
            this.canvas.addEventListener('mousemove', (e) => {
                const rect = this.canvas.getBoundingClientRect();
                const scaleX = this.canvas.width / rect.width;
                const scaleY = this.canvas.height / rect.height;
                const x = (e.clientX - rect.left) * scaleX;
                const y = (e.clientY - rect.top) * scaleY;
                
                this.handleHover(x, y);
            });
            
            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.key === ' ' || e.key === 'Enter') {
                    e.preventDefault();
                    if (this.gameState.canRoll && !this.gameState.hasRolled) {
                        this.rollDice();
                    }
                }
            });
        }

        /**
         * ==============================================
         * HANDLE CLICK
         * ==============================================
         */
        handleClick(x, y) {
            // Check if game is over
            if (this.gameState.isGameOver) {
                // Click to restart or something
                return;
            }
            
            // Check if click is on dice
            const diceX = this.canvasWidth - LUDO_CONFIG.DICE_SIZE - 20;
            const diceY = 20;
            const diceSize = LUDO_CONFIG.DICE_SIZE;
            
            if (x >= diceX && x <= diceX + diceSize &&
                y >= diceY && y <= diceY + diceSize) {
                if (this.gameState.canRoll && !this.gameState.hasRolled) {
                    this.rollDice();
                    return;
                }
            }
            
            // Check if click is on a token
            const playerId = this.gameState.currentTurn;
            const player = this.players[playerId];
            
            if (!player || !player.isHuman) return;
            
            // Check each token for click
            for (let token of player.tokens) {
                if (token.isFinished) continue;
                const dx = x - token.x;
                const dy = y - token.y;
                const dist = Math.sqrt(dx * dx + dy * dy);
                
                if (dist < this.tokenRadius + 5) {
                    // Token clicked
                    this.selectToken(token);
                    return;
                }
            }
            
            // Check if click is on a valid move target
            if (this.gameState.selectedToken && this.gameState.availableMoves.length > 0) {
                const moves = this.gameState.availableMoves;
                for (let move of moves) {
                    if (move.toTrackIndex >= 0 && move.toTrackIndex < TRACK_POSITIONS.length) {
                        const pos = TRACK_POSITIONS[move.toTrackIndex];
                        const dx = x - pos.x;
                        const dy = y - pos.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        
                        if (dist < this.cellSize / 2) {
                            this.executeMove(move);
                            this.gameState.selectedToken = null;
                            this.gameState.availableMoves = [];
                            
                            // Check if need to end turn
                            if (this.gameState.diceValue !== 6) {
                                setTimeout(() => {
                                    this.endTurn();
                                }, 400);
                            } else {
                                this.gameState.hasRolled = false;
                                this.gameState.canRoll = true;
                            }
                            
                            return;
                        }
                    }
                }
            }
            
            // Clear selection if clicking elsewhere
            this.gameState.selectedToken = null;
            this.gameState.availableMoves = [];
            this.render();
        }

        /**
         * ==============================================
         * HANDLE HOVER
         * ==============================================
         */
        handleHover(x, y) {
            // Update cursor
            let hovered = false;
            
            // Check dice hover
            const diceX = this.canvasWidth - LUDO_CONFIG.DICE_SIZE - 20;
            const diceY = 20;
            const diceSize = LUDO_CONFIG.DICE_SIZE;
            
            if (x >= diceX && x <= diceX + diceSize &&
                y >= diceY && y <= diceY + diceSize &&
                this.gameState.canRoll && !this.gameState.hasRolled) {
                this.canvas.style.cursor = 'pointer';
                hovered = true;
            }
            
            // Check token hover
            if (!hovered) {
                const playerId = this.gameState.currentTurn;
                const player = this.players[playerId];
                
                if (player && player.isHuman) {
                    for (let token of player.tokens) {
                        if (token.isFinished) continue;
                        const dx = x - token.x;
                        const dy = y - token.y;
                        const dist = Math.sqrt(dx * dx + dy * dy);
                        
                        if (dist < this.tokenRadius + 5) {
                            this.canvas.style.cursor = 'pointer';
                            hovered = true;
                            break;
                        }
                    }
                }
            }
            
            if (!hovered) {
                // Check if hovering over a valid move target
                if (this.gameState.selectedToken && this.gameState.availableMoves.length > 0) {
                    const moves = this.gameState.availableMoves;
                    for (let move of moves) {
                        if (move.toTrackIndex >= 0 && move.toTrackIndex < TRACK_POSITIONS.length) {
                            const pos = TRACK_POSITIONS[move.toTrackIndex];
                            const dx = x - pos.x;
                            const dy = y - pos.y;
                            const dist = Math.sqrt(dx * dx + dy * dy);
                            
                            if (dist < this.cellSize / 2) {
                                this.canvas.style.cursor = 'pointer';
                                hovered = true;
                                break;
                            }
                        }
                    }
                }
            }
            
            if (!hovered) {
                this.canvas.style.cursor = 'default';
            }
        }

        /**
         * ==============================================
         * SELECT TOKEN
         * ==============================================
         */
        selectToken(token) {
            if (this.gameState.isGameOver) return;
            if (this.gameState.selectedToken === token) {
                // Deselect
                this.gameState.selectedToken = null;
                this.gameState.availableMoves = [];
                this.render();
                return;
            }
            
            if (this.gameState.hasRolled) {
                const moves = this.getValidMoves(
                    this.gameState.currentTurn,
                    token.id,
                    this.gameState.diceValue
                );
                
                if (moves.length > 0) {
                    this.gameState.selectedToken = token;
                    this.gameState.availableMoves = moves;
                    
                    if (this.callbacks.onTokenSelect) {
                        this.callbacks.onTokenSelect(token, moves);
                    }
                    
                    this.render();
                } else {
                    // No valid moves for this token
                    if (typeof LudoAudioEngine !== 'undefined') {
                        LudoAudioEngine.playError();
                    }
                }
            }
        }

        /**
         * ==============================================
         * GAME CONTROL METHODS
         * ==============================================
         */
        
        /**
         * Start the game
         */
        startGame() {
            if (this.gameState.status === 'playing') return;
            
            this.gameState.status = 'playing';
            this.gameState.currentTurn = 1;
            this.gameState.canRoll = true;
            this.gameState.hasRolled = false;
            this.gameState.isGameOver = false;
            this.gameState.winner = null;
            
            // Reset tokens
            this.initializeTokens();
            
            if (this.callbacks.onGameStateChange) {
                this.callbacks.onGameStateChange('playing');
            }
            
            this.render();
            
            // If player 1 is AI, start AI turn
            if (!this.players[1].isHuman) {
                setTimeout(() => this.aiTurn(), 500);
            }
        }

        /**
         * Reset the game
         */
        resetGame() {
            this.gameState.status = 'waiting';
            this.gameState.currentTurn = 1;
            this.gameState.diceValue = 0;
            this.gameState.diceRolling = false;
            this.gameState.selectedToken = null;
            this.gameState.availableMoves = [];
            this.gameState.moveHistory = [];
            this.gameState.turnNumber = 0;
            this.gameState.winner = null;
            this.gameState.isGameOver = false;
            this.gameState.canRoll = true;
            this.gameState.hasRolled = false;
            this.gameState.forcedCapture = false;
            
            this.initializeTokens();
            this.render();
            
            if (this.callbacks.onGameStateChange) {
                this.callbacks.onGameStateChange('reset');
            }
        }

        /**
         * ==============================================
         * EVENT CALLBACK REGISTRATION
         * ==============================================
         */
        on(event, callback) {
            if (this.callbacks.hasOwnProperty(event)) {
                this.callbacks[event] = callback;
            }
        }

        /**
         * ==============================================
         * STATE GETTERS
         * ==============================================
         */
        getState() {
            return {
                status: this.gameState.status,
                currentTurn: this.gameState.currentTurn,
                diceValue: this.gameState.diceValue,
                isGameOver: this.gameState.isGameOver,
                winner: this.gameState.winner,
                turnNumber: this.gameState.turnNumber,
                player1: {
                    name: this.players[1].name,
                    homeCount: this.players[1].homeCount,
                    tokens: this.players[1].tokens.map(t => ({
                        id: t.id,
                        isHome: t.isHome,
                        isFinished: t.isFinished,
                        trackIndex: t.trackIndex,
                        isActive: t.isActive,
                    })),
                },
                player2: {
                    name: this.players[2].name,
                    homeCount: this.players[2].homeCount,
                    tokens: this.players[2].tokens.map(t => ({
                        id: t.id,
                        isHome: t.isHome,
                        isFinished: t.isFinished,
                        trackIndex: t.trackIndex,
                        isActive: t.isActive,
                    })),
                },
            };
        }

        /**
         * ==============================================
         * CANVAS ROUND RECT POLYFILL
         * ==============================================
         */
        roundRectPolyfill() {
            if (!CanvasRenderingContext2D.prototype.roundRect) {
                CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, radii) {
                    const r = typeof radii === 'number' ? radii : (radii || 0);
                    this.moveTo(x + r, y);
                    this.arcTo(x + w, y, x + w, y + h, r);
                    this.arcTo(x + w, y + h, x, y + h, r);
                    this.arcTo(x, y + h, x, y, r);
                    this.arcTo(x, y, x + w, y, r);
                    return this;
                };
            }
        }

        /**
         * ==============================================
         * DESTROY / CLEANUP
         * ==============================================
         */
        destroy() {
            if (this.animationId) {
                cancelAnimationFrame(this.animationId);
                this.animationId = null;
            }
            this.canvas.removeEventListener('click', this.handleClick);
            this.canvas.removeEventListener('touchstart', this.handleTouch);
            this.canvas.removeEventListener('mousemove', this.handleHover);
        }
    }

    /**
     * ==============================================
     * EXPOSE TO GLOBAL SCOPE
     * ==============================================
     */
    // Apply polyfill
    if (typeof CanvasRenderingContext2D !== 'undefined') {
        if (!CanvasRenderingContext2D.prototype.roundRect) {
            CanvasRenderingContext2D.prototype.roundRect = function(x, y, w, h, radii) {
                const r = typeof radii === 'number' ? radii : (radii || 0);
                this.moveTo(x + r, y);
                this.arcTo(x + w, y, x + w, y + h, r);
                this.arcTo(x + w, y + h, x, y + h, r);
                this.arcTo(x, y + h, x, y, r);
                this.arcTo(x, y, x + w, y, r);
                return this;
            };
        }
    }

    // Expose globally
    window.LudoEngine = LudoEngine;
    window.LUDO_CONFIG = LUDO_CONFIG;
    window.TRACK_POSITIONS = TRACK_POSITIONS;
    window.SAFE_CELLS = SAFE_CELLS;

    console.log('LudoEngine: Loaded successfully');

})();
