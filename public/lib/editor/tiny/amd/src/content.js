// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Module to assist with creation and management of content.
 *
 * @module     editor_tiny/content
 * @copyright  Andrew Lyons <andrew@nicols.co.uk>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Add MathML support to the editor.
 *
 * @param {TinyMCE} editor
 */
export const addMathMLSupport = (editor) => {
    const getNodeType = (node) => {
        // At root level (direct child of body), always use a block wrapper so TinyMCE
        // does not receive a bare inline contenteditable="false" node outside a root block
        // (which causes Firefox to insert extraneous <br> elements).
        if (!node.parent || node.parent.name === 'body' || node.parent.name === '#document') {
            return 'tiny-math-block';
        }
        // Prefer the standard MathML display attribute over inline CSS.
        const display = node.attr('display')?.toLowerCase();
        if (display === 'block') {
            return 'tiny-math-block';
        }
        if (display === 'inline') {
            return 'tiny-math-span';
        }
        const style = node.attr('style');
        const match = /display\s*:\s*(block|inline)/i.exec(style ?? '');
        if (match) {
            return match[1].toLowerCase() === 'block' ? 'tiny-math-block' : 'tiny-math-span';
        }
        // Inside a block or phrasing container (e.g. <p>), default to an inline wrapper
        // so the parent block is not split and MathML Core's inline default is preserved.
        return 'tiny-math-span';
    };

    editor.on('PreInit', () => {
        editor.schema.addCustomElements({
            // Add support for MathML by defining some tiny-math blocks which extends SPAN/DIV.
            // Note: This is blind support and does not check the child content.
            // Any invalid markup will be accepted.
            // Note: We use the same names as the Tiny Premium Math plugin to avoid conflicts if both are enabled.
            math: {
                'extends': 'span',
                'attributes': [
                    '@global',
                    'display',
                    'displaystyle',
                    'xmlns',
                    'mathbackground',
                    'mathcolor',
                    'mathsize',
                    'mathvariant',
                    'scriptlevel',
                ],
            },
            'tiny-math-span': {
                'extends': "span",
            },
            'tiny-math-block': {
                'extends': "div",
            },
        });

        // Add a Parser filter to wrap math nodes in a tiny-math-[block|span] element.
        editor.parser.addNodeFilter('math', (nodes) => nodes.forEach((node) => {
            if (node.parent) {
                if (
                    node.parent.name === 'tiny-math-block' ||
                    node.parent.name === 'tiny-math-span' ||
                    node.parent.name === 'tiny-math-inline'
                ) {
                    // Already wrapped (tiny-math-inline is used by the TinyMCE Premium Math plugin).
                    return;
                }
            }

            const displayMode = getNodeType(node);
            node.wrap(editor.editorManager.html.Node.create(displayMode, {
                contenteditable: 'false',
            }));
        }));

        // Add a Serializer filter to unwrap the temporary tiny-math-[block|span] wrapper without injecting inline display styles.
        editor.serializer.addNodeFilter('tiny-math-span, tiny-math-block', (nodes) => nodes.forEach((node) => {
            node.unwrap();
        }));
    });
};

/**
 * Add SVG support to the editor.
 *
 * @param {TinyMCE} editor
 */
export const addSVGSupport = (editor) => {
    editor.on('PreInit', () => {
        editor.schema.addCustomElements({
            // Add support for SVG by defining an SVG tag which extends DIV.
            // Note: This is blind support and does not check the child content.
            // Any invalid markup will be accepted.
            svg: {
                'extends': "div",
                'attributes': [
                    "id",
                    "tabindex",
                    "lang",
                    "xml:space",
                    "class",
                    "style",
                    "x",
                    "y",
                    "width",
                    "height",
                    "viewBox",
                    "preserveAspectRatio",
                    "transform",
                ],
            },
            'tiny-svg-block': {
                'extends': "div",
            },
        });

        editor.parser.addNodeFilter('svg', (nodes) => nodes.forEach((node) => {
            node.wrap(editor.editorManager.html.Node.create('tiny-svg-block', {
                contenteditable: 'false',
            }));
        }));
        editor.serializer.addNodeFilter('tiny-svg-block', (nodes) => nodes.forEach((node) => {
            node.unwrap();
        }));
    });
};
