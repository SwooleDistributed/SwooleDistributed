/*
 Copyright (c) 2005 JSON.org

 Permission is hereby granted, free of charge, to any person obtaining a copy
 of this software and associated documentation files (the "Software"), to deal
 in the Software without restriction, including without limitation the rights
 to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 copies of the Software, and to permit persons to whom the Software is
 furnished to do so, subject to the following conditions:

 The Software shall be used for Good, not Evil.

 THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 SOFTWARE.
 */

/*
 The global object JSON contains two methods.

 JSON.stringify(value) takes a JavaScript value and produces a JSON text.
 The value must not be cyclical.

 JSON.parse(text) takes a JSON text and produces a JavaScript value. It will
 return false if there is an error.
 */

if (typeof JSON != 'object' || window.navigator.userAgent.match('Gecko/') !== null) {
    var JSON = function () {
        var m = {
                '\b': '\\b',
                '\t': '\\t',
                '\n': '\\n',
                '\f': '\\f',
                '\r': '\\r',
                '"': '\\"',
                '\\': '\\\\'
            },
            s = {
                'boolean': function (x) {
                    return String(x);
                },
                number: function (x) {
                    return isFinite(x) ? String(x) : 'null';
                },
                string: function (x) {
                    if (/["\\\x00-\x1f]/.test(x)) {
                        x = x.replace(/([\x00-\x1f\\"])/g, function (a, b) {
                            var c = m[b];
                            if (c) {
                                return c;
                            }
                            c = b.charCodeAt();
                            return '\\u00' +
                                Math.floor(c / 16).toString(16) +
                                (c % 16).toString(16);
                        });
                    }
                    return '"' + x + '"';
                },
                object: function (x) {
                    if (x) {
                        var a = [], b, f, i, l, v;
                        if (x instanceof Array) {
                            a[0] = '[';
                            l = x.length;
                            for (i = 0; i < l; i += 1) {
                                v = x[i];
                                f = s[typeof v];
                                if (f) {
                                    v = f(v);
                                    if (typeof v == 'string') {
                                        if (b) {
                                            a[a.length] = ',';
                                        }
                                        a[a.length] = v;
                                        b = true;
                                    }
                                }
                            }
                            a[a.length] = ']';
                        } else if (typeof x.hasOwnProperty === 'function') {
                            a[0] = '{';
                            for (i in x) {
                                if (x.hasOwnProperty(i)) {
                                    v = x[i];
                                    f = s[typeof v];
                                    if (f) {
                                        v = f(v);
                                        if (typeof v == 'string') {
                                            if (b) {
                                                a[a.length] = ',';
                                            }
                                            a.push(s.string(i), ':', v);
                                            b = true;
                                        }
                                    }
                                }
                            }
                            a[a.length] = '}';
                        } else {
                            return;
                        }
                        return a.join('');
                    }
                    return 'null';
                }
            };
        return {
            copyright: '(c)2005 JSON.org',
            license: 'http://www.JSON.org/license.html',
            /*
             Stringify a JavaScript value, producing a JSON text.
             */
            stringify: function (v) {
                var f = s[typeof v];
                if (f) {
                    v = f(v);
                    if (typeof v == 'string') {
                        return v;
                    }
                }
                return null;
            },
            /*
             Parse a JSON text, producing a JavaScript value.
             It returns false if there is a syntax error.
             */
            parse: function (text) {
                // replacement code for the code on the bottom of this function-body
                return eval('(' + text + ')');

                // this was the original code, but it no longer is in use because it could
                // crash the safari browser. This code is no longer reached because of
                // the return statement above
                /*
                 try {
                 return !(/[^,:{}\[\]0-9.\-+Eaeflnr-u \n\r\t]/.test(
                 text.replace(/"(\\.|[^"\\])*"/g, ''))) &&
                 eval('(' + text + ')');
                 } catch (e) {
                 return false;
                 }
                 */
            }
        };
    }();
}
