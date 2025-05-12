  (function(){

      var app = {

        s:{},
        CURRENT_URL:"",
        IMG:null,

        mouse:{
          down:false,
          moving:false,
          start_coords:[0,0],
          end_coords:[0,0],

        },

        init:function(s){
            let _this = this;
          for(var key in s){
            _this.s[key] = s[key];
          }
          _this.s.id = _this.createID();

            _this.create();
            
            var urlInput = document.querySelector('[name="img-url-'+_this.s.id+'"]');
            urlInput.value = "https://aadl.org/sites/default/files/photos/wineberg_1469.jpg";
            _this.loadImg();

            _this.setListeners();
            

            return _this;

        },

        createID:function(){
            var letters = ['a','b','c','d','e','f'];
            var rand1 = Math.floor(Math.random() * 1000);
            var rand2 = Math.floor(Math.random() * 777);
            var rand3 = Math.floor(Math.random() * 5);
          var id = letters[Math.floor(Math.random() * 5)] + letters[Math.floor(Math.random() * 5)] + ((new Date().valueOf() * rand2) - rand1);

          return id;
        },


        loadImg: function(){
            let _this = this;
          var urlInput = document.querySelector('[name="img-url-'+_this.s.id+'"]');
          var img = document.createElement('IMG');
          console.log(img);
          img.addEventListener("load", (e) => {
                _this.IMG = img;
                _this.resize();
          });
          img.onerror = function(e){
            console.log(e);
             _this.IMG = false;
          }
          img.src = urlInput.value;

        },


        resize:function(){
          var _this = this;
          var c = document.querySelector("#"+_this.s.id);
          var container = document.querySelector(_this.s.target);
          container.style.width = "100%;";
          var w = container.offsetWidth;
          c.width = w;

          if (_this.IMG !== false) {

              var nw  = _this.IMG.naturalWidth;
              var nh = _this.IMG.naturalHeight;

              var ratio = nw/w;
              var h = nh/ratio;
              c.height = h;
          }

          _this.render();

        },


        create:function(){

          var _this = this;

          var html = `<div class="img-url"><label for="img-url-${_this.s.id}">Image URL: </label><input type="text" name="img-url-${_this.s.id}"></div>
          <div class="canvas-container"><canvas id="${_this.s.id}"></canvas></div>
          <div class="coordinate-container"><label for="coordinates-${_this.s.id}">Box location data: </label><input type="text" name="coordinates-${_this.s.id}"></div>`;

          document.querySelector(_this.s.target).appendChild(document.createRange().createContextualFragment(html));
           

        },

        render:function(){
          let _this = this;

            //render canvas
            var c = document.querySelector("#"+_this.s.id);
            var ctx = c.getContext("2d");
            ctx.clearRect(0, 0, c.width, c.height);

            ctx.drawImage(_this.IMG, 0, 0, c.width, c.height);
             
            ctx.beginPath();
            ctx.lineWidth = "1";
            ctx.strokeStyle = "red";

            /*
            ctx.rect(
              c.offsetWidth*0.272,
              c.offsetHeight*0.46938775510204084,
              c.offsetWidth*0.496, 
              c.offsetHeight*0.15743440233236153,
            );*/

            ctx.rect(
              _this.mouse.start_coords[0],
              _this.mouse.start_coords[1],
              (_this.mouse.end_coords[0]-_this.mouse.start_coords[0]), 
              (_this.mouse.end_coords[1]-_this.mouse.start_coords[1])
            );
            ctx.stroke();
            

            var output = document.querySelector('[name="coordinates-'+_this.s.id+'"]');

            output.value = _this.getRatioDataAsString();

        },

        setListeners: function(){

          let _this = this;
          window.addEventListener('resize', function(){
            _this.resize();
          });


          urlInput = document.querySelector('[name="img-url-'+_this.s.id+'"]');
            urlInput.addEventListener('change',function(){
              _this.loadImg();
            });



            let c = document.querySelector("#"+_this.s.id);
            let ctx = c.getContext("2d");
            //mouse down

            c.addEventListener('mousedown',function(e){
              e = e || window.e;
                console.log(e);
                _this.mouse.down = true;
                _this.mouse.start_coords = [e.offsetX, e.offsetY];

            });

            /* mouse events */
            c.addEventListener('mousemove',function(e){
              e = e || window.e;
              if (_this.mouse.down){
                   _this.mouse.end_coords = [e.offsetX, e.offsetY];
                   console.log(_this.mouse.end_coords);
              }
              _this.render();
            });

            c.addEventListener('mouseup',function(e){
              e = e || window.e;
              if (_this.mouse.down){
                   _this.mouse.end_coords = [e.offsetX, e.offsetY];
              }
              _this.mouse.down = false;
            });

            c.addEventListener('mouseout',function(e){
              e = e || window.e;
              _this.mouse.down = false;
            });





        },


        getRatioDataAsString:function(){
          var _this = this;
          var c = document.querySelector("#"+_this.s.id);
          var w = c.offsetWidth;
            
            var ratios = {};
            ratios.x = _this.mouse.start_coords[0]/c.offsetWidth;
            ratios.y = _this.mouse.start_coords[1]/c.offsetHeight;
            ratios.w = (_this.mouse.end_coords[0]-_this.mouse.start_coords[0])/c.offsetWidth;
            ratios.h = (_this.mouse.end_coords[1]-_this.mouse.start_coords[1])/c.offsetHeight;

            return ratios.x +","+ ratios.y +","+ ratios.w +","+ ratios.h;

        },



      }

      window.aadl_box_locator = function(s){
        return app.init(s);
      };


  })();

  var boxDrag = window.aadl_box_locator({
    target:".coordinate-data-area"
  });