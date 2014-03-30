all: Net.phar

Net:
	curl -s http://download.pear.php.net/package/Net_DNS2-1.3.2.tgz | tar xz --strip-components=1 Net_DNS2-1.3.2/Net

Net.phar: Net
	php -d phar.readonly=0 -r '$$p = new Phar("Net.phar"); $$p->buildFromDirectory("Net", ""); $$p->setStub("<?php __HALT_COMPILER();");'

.PHONY: all clean distclean

clean:
	rm -r Net
	rm Net.phar

distclean:
	rm -r Net
